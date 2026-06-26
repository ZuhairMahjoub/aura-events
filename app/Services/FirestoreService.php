<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;

class FirestoreService
{
    protected $client;
    protected $projectId;
    protected $token;

    public function __construct()
    {
        $path = storage_path('app/firebase/royal-event-app-firebase-adminsdk-fbsvc-02740649a6.json');
        $credentials = json_decode(file_get_contents($path), true);
        
        $this->projectId = $credentials['project_id'];
        
        $serviceAccount = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/datastore',
            $credentials
        );
        
        $token = $serviceAccount->fetchAuthToken();
        $this->token = $token['access_token'];
        
        $this->client = new Client([
            'base_uri' => "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/(default)/documents/",
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function setDocument(string $collection, string $docId, array $data): void
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $fields[$key] = ['arrayValue' => ['values' => array_map(fn($v) => ['stringValue' => $v], $value)]];
            } else {
                $fields[$key] = ['stringValue' => (string) $value];
            }
        }

        $this->client->patch($collection . '/' . $docId, [
            'json' => ['fields' => $fields],
        ]);
    }
}