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
    public function getMessages(string $chatId, int $limit = 30, ?string $startAfterDocId = null): array
    {
        // بناء مسار الاستعلام عبر الـ REST API
        $query = [
            'structuredQuery' => [
                'from' => [
                    ['collectionId' => 'messages']
                ],
                'orderBy' => [
                    [
                        'field' => ['fieldPath' => 'created_at'],
                        'direction' => 'DESCENDING'
                    ]
                ],
                'limit' => $limit,
            ]
        ];

        // إذا وُجد كيرسر (الصفحة السابقة)، نضيفه للبدء من بعده
        if ($startAfterDocId) {
            // ملاحظة: لجلب مستند البداية عبر الـ REST API نحتاج لاسم المستند الكامل
            $responseDoc = $this->client->get("chats/{$chatId}/messages/{$startAfterDocId}");
            $docData = json_decode($responseDoc->getBody()->getContents(), true);
            
            if (isset($docData['createTime'])) {
                $query['structuredQuery']['startAt'] = [
                    'values' => [
                        ['timestampValue' => $docData['createTime']]
                    ],
                    'before' => false // تعني startAfter
                ];
            }
        }

        $response = $this->client->post("chats/{$chatId}/messages:runQuery", [
            'json' => $query
        ]);

        $results = json_decode($response->getBody()->getContents(), true);
        $messages = [];

        foreach ($results as $item) {
            if (isset($item['document'])) {
                $doc = $item['document'];
                // استخراج الـ ID من اسم المستند الكامل
                $nameParts = explode('/', $doc['name']);
                $docId = end($nameParts);

                // تحويل حقول الـ Firestore REST إلى مصفوفة عادية
                $fields = [];
                if (isset($doc['fields'])) {
                    foreach ($doc['fields'] as $key => $val) {
                        $fields[$key] = array_values($val)[0] ?? null;
                    }
                }

                $messages[] = array_merge(['id' => $docId], $fields);
            }
        }

        return $messages;
    }
}