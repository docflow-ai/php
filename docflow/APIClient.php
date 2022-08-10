<?php

namespace DocFlow;


class ObjectId implements \Serializable, \JsonSerializable {

    public string $oid;

    public function __construct(string $id) {
        preg_match('/^[a-f0-9]{24}$/i', $id, $matches);
        if (empty($matches)) {
            throw new \InvalidArgumentException("Error parsing ObjectId string: {$id}");
        }

        $this->oid = $id;
    }

    public function __toString() : string {
        return $this->oid;
    }

    public function getTimestamp(): int {
        return intval(substr($this->oid, 0, 8), 16);
    }

    public function jsonSerialize(): array {
        return ['$oid' => $this->oid];
    }

    public function serialize(): string {
        return serialize(['oid' => $this->oid]);
    }
    public function unserialize($serialized): void {
        $data = unserialize($serialized);
        $this->oid = $data['oid'];
    }
}


class APIClient {

    private const API_URL = 'https://api.docflow.ai';
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.000\Z';
    private bool $isLoggedIn = false;
    private \stdClass $userData;
    private RESTClient $session;

    public function __construct() {
        $this->session = new RESTClient;
    }

    private function checkLoggedIn() : void {
        if (!$this->isLoggedIn()) {
            throw new APIClientException("You are not logged in!");
        }
    }

    public function login(string $email, string $password) : bool {
        $credentials = ['email' => $email, 'password' => hash('sha256', $password)];

        $response = $this->session->post(
            self::API_URL . '/user/login',
            $credentials
        );

        if (isset($response->success) && $response->success == 1) {
            $this->isLoggedIn = true;
            $this->userData = $response->user;
            $this->userData->_id = new ObjectId($this->userData->_id);
            $this->userData->currentOwnerId = new ObjectId($this->userData->currentOwnerId);
            return true;
        }
        return false;
    }

    public function logout() : bool {
        $this->session->post(self::API_URL . '/user/logout', []);
        return true;
    }

    public function isLoggedIn() : bool {
        return $this->isLoggedIn;
    }

    public function getOwner() : \stdClass {
        $this->checkLoggedIn();
        $current = current(array_filter($this->getOwners(), function ($obj) {
            return $obj->id == $this->userData->currentOwnerId;
        }));

        return (object)[
            'id' => $this->userData->currentOwnerId,
            'name' => $this->userData->currentOwnerName,
            'iban' => $this->userData->currentOwnerIban,
            'mailbox' => $this->userData->currentOwnerMailbox,
            'role' => $current->role,
        ];
    }

    public function hasOwner(ObjectId $objectId) : bool {
        foreach ($this->getOwners() as $owner) {
            if ($owner->id == $objectId) {
                return true;
            }
        }
        return false;
    }

    public function changeOwner(ObjectId $ownerId) : bool {
        $this->checkLoggedIn();

        if (!$this->hasOwner($ownerId)) {
            throw new APIClientException("You do not have rights for this owner (project)");
        }

        $response = $this->session->post(
            self::API_URL . '/user/change-owner',
            ['id' => (string)$ownerId]
        );

        if (isset($response->success) && $response->success == 1) {
            unset($response->success);
            $this->userData = $response;
            $this->userData->_id = new ObjectId($this->userData->_id);
            $this->userData->currentOwnerId = new ObjectId($this->userData->currentOwnerId);
            return true;
        }

        return false;
    }

    public function getOwners() : array {
        $this->checkLoggedIn();

        return array_map(function($obj) {
            $obj->id = new ObjectId($obj->id);
            return $obj;
        }, $this->userData->owners);
    }

    public function getDocumentTypes() : array {
        $this->checkLoggedIn();
        $response = $this->session->get(self::API_URL . '/doctypes');
        if (is_array($response)) {
            return array_map(function ($obj) {
                return $obj->id;
            }, $response);
        }
        return [];
    }

    public function uploadDocument(string $filepath, string $doctype = null, string $name = null) : ?ObjectId {
        $this->checkLoggedIn();

        if (!file_exists($filepath)) {
            throw new APIClientException("File {$filepath} not exists");
        }

        if (!in_array($doctype, $this->getDocumentTypes())) {
            throw new APIClientException("Wrong doctype");
        }

        $origin = $this->prepareDocumentPages($filepath, basename($filepath));

        if (!count($origin)) {
            throw new APIClientException("Document with no pages!");
        }

        $response = $this->session->post(
            self::API_URL . '/document',
            [
                'name' => basename($filepath),
                'documentType' => $doctype,
                'process' => 0,
                'origin' => $origin
            ]
        );

        if (isset($response->success) && $response->success == 1) {
            return new ObjectId($response->id);
        }

        return null;
    }

    public function getDocumentInfo(ObjectId $id) : \stdClass {
        $this->checkLoggedIn();

        $response = $this->session->get(self::API_URL . '/document/info/' . (string)$id);
        if ($response) {
            $ownerId = new ObjectId($response->ownerId);
            if ($ownerId != $this->getOwner()->id) {
                $this->changeOwner($ownerId);
            }

            $doc = $this->session->get(self::API_URL . '/document/' . (string)$id);

            return $this->prepareDocumentInfo($doc);
        } else {
            throw new APIClientException("No response");
        }
    }

    public function getDocumentList(\DateTime $from, \DateTime $to, string $doctype = null) : array {
        $response = $this->session->post(
            self::API_URL . '/documents/ids',
            [
                'project' => $doctype,
                'sortField' => '_id',
                'sortOrder' => -1,
                'filters' => ['createdAtFrom' => $from->format(self::DATE_FORMAT), 'createdAtTo' => $to->format(self::DATE_FORMAT)]
            ]
        );

        if (isset($response->data) && $response->total > 0) {
            return array_map(function ($id) {
                return new ObjectId($id);
            }, $response->data);
        }

        return [];
    }

    private function prepareDocumentInfo(\stdClass $doc) : \stdClass {
        return (object)[
            'id' => new ObjectId($doc->_id),
            'userId' => $doc->userId ? new ObjectId($doc->userId) : null,
            'step' => $doc->step,
            'doctype' => $doc->doctype,
            'name' => $doc->name,
            'predicted' => $doc->predicted,
            'updatedAt' => $doc->updatedAt,
            'createdAt' => $doc->createdAt,
            'pages' => array_map(function($page) {
                $page->fileId = $page->_id ? new ObjectId($page->_id) : null;
                unset($page->_id, $page->vision, $page->type);
                return $page;
            }, $doc->pages),
            'fields' => array_map(function($obj) {
                unset($obj->bboxes);
                if (!empty($obj->bounds) && !empty($obj->bounds->id)) {
                    $obj->bounds->id = $obj->bounds->id ? new ObjectId($obj->bounds->id) : null;
                }
                return $obj;
            }, !empty($doc->fields) ? $doc->fields : [])
        ];
    }

    private function prepareDocumentPages(string $filepath, string $name) : array {
        $pages = [];
        $mimetype = mime_content_type($filepath);

        if (in_array($mimetype, ['application/pdf'])) {
            $pdf = new \setasign\Fpdi\Fpdi;
            $pageCount = $pdf->setSourceFile($filepath);

            if ($pageCount > 1) {
                // Split each page into a new PDF
                for ($i = 1; $i <= $pageCount; $i++) {
                    $new_pdf = new \setasign\Fpdi\Fpdi;
                    $new_pdf->AddPage();
                    $new_pdf->setSourceFile($filepath);
                    $new_pdf->useTemplate($new_pdf->importPage($i));

                    try {
                        $b64body = base64_encode($new_pdf->Output('S'));
                        $data = "data:{$mimetype};base64,{$b64body}";
                        array_push($pages, [
                            'data' => $data,
                            'name' => $name,
                            'hash' => sha1($data),
                            'page' => $i,
                            'type' => $mimetype,
                        ]);
                    } catch (\Exception $e) {
                        throw new APIClientException($e->getMessage());
                    }
                }
            } else {
                $b64body = base64_encode(file_get_contents($filepath));
                $data = "data:{$mimetype};base64,{$b64body}";
                array_push($pages, [
                    'data' => $data,
                    'name' => $name,
                    'hash' => sha1($data),
                    'page' => 1,
                    'type' => $mimetype,
                ]);
            }
        } elseif (in_array($mimetype, ['image/jpg', 'image/jpeg', 'image/png'])) {
            $b64body = base64_encode(file_get_contents($filepath));
            $data = "data:{$mimetype};base64,{$b64body}";
            array_push($pages, [
                'data' => $data,
                'name' => $name,
                'hash' => sha1($data),
                'page' => 1,
                'type' => $mimetype,
            ]);
        } else {
            throw new APIClientException("Unsupported type {$mimetype}!");
        }
        return $pages;
    }
}

class RESTClient {

    public const TIMEOUT = 5;
    public const DEFAULT_POST_HEADERS = ['Content-Type: application/json'];
    private $cookie;

    public function get(string $url, $headers = self::DEFAULT_POST_HEADERS) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        if (!empty($this->cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, http_build_query($this->cookie, '', '; '));
        }
        return $this->processResponse($curl);
    }

    public function post(string $url, array $data, $headers = self::DEFAULT_POST_HEADERS) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($this->cookie)) {
            curl_setopt($curl, CURLOPT_COOKIE, http_build_query($this->cookie, '', '; '));
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
        return $this->processResponse($curl);
    }

    private function processResponse($curl) {
        $response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code == 200) {
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $this->cookie = self::parseCookies($header);

            try {
                $body = json_decode($body);
                return $body;
            } catch (\Exception $e) {
                throw new RESTClientException("Wrong response");
            }
        } else {
            throw new RESTClientException("API Error with code {$http_code}.");
        }
    }

    private static function parseCookies($header) : array {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
        $cookies = [];
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        return $cookies;
    }
}

class APIClientException extends \Exception  {}
class RESTClientException extends \Exception  {}