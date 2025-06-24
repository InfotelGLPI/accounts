<?php
require_once('../../../vendor/autoload.php');

$project = 'o:infotelGLPI:p:GLPI_accounts:r:locales-glpi-pot--master';
$token = 'Bearer 1/xxxxxxxxxxxxxxxx';

$client = new GuzzleHttp\Client();

$response = $client->request('GET', 'https://rest.api.transifex.com/projects/o%3AinfotelGLPI%3Ap%3AGLPI_accounts/languages', [
    'headers' => [
        'accept' => 'application/vnd.api+json',
        'authorization' => $token,
    ],
]);

$data = $response->getBody();

$datajson = json_decode($data, true);

if (isset($datajson['data'])) {
    foreach ($datajson['data'] as $langs) {
        if (isset($langs['attributes']['code'])) {
            $lang = $langs['attributes']['code'];
//            $uri = "https://rest.api.transifex.com/resource_language_stats/o%3AinfotelGLPI%3Ap%3AGLPI_accounts%3Ar%3Alocales-glpi-pot--master%3Al%3A".$lang;
            $response = $client->request('GET', 'https://rest.api.transifex.com/resource_language_stats/o%3AinfotelGLPI%3Ap%3AGLPI_accounts%3Ar%3Alocales-glpi-pot--master%3Al%3A'.$lang, [
                'headers' => [
                    'accept' => 'application/vnd.api+json',
                    'authorization' => $token,
                ],
            ]);

            $datalang = $response->getBody();
            $datajsonlang = json_decode($datalang, true);

            if (isset($datajsonlang['data'])
                && $datajsonlang['data']['attributes']['untranslated_words'] == 0
                && $datajsonlang['data']['attributes']['untranslated_strings'] == 0) {
                $response = $client->request('POST', 'https://rest.api.transifex.com/resource_translations_async_downloads', [
                    'body'    => '{"data":{"attributes":{"callback_url":null,
              "content_encoding":"text",
              "file_type":"default",
              "mode":"default","pseudo":false},
              "relationships":{"language":{"data":{"type":"languages","id":"l:' . $lang . '"}},
              "resource":{"data":{"type":"resources","id":"' . $project . '"}}},
              "type":"resource_translations_async_downloads"}}',
                    'headers' => [
                        'accept'        => 'application/vnd.api+json',
                        'authorization' => $token,
                        'content-type'  => 'application/vnd.api+json',
                    ],
                ]);
                $data     = $response->getBody();

                $datajson = json_decode($data, true);
                //print_r($datajson);
                if (isset($datajson['data']['links']['self'])) {
                    $link = $datajson['data']['links']['self'];

                    $resource = fopen('../locales/' . $lang . '.po', 'w');

                    $response = $client->request('GET', $link, [
                        'headers' => [
                            'Cache-Control' => 'no-cache',
                            'authorization' => $token,
                            'content-type'  => 'application/txt',
                        ],
                        'sink'    => $resource,
                    ]);

                    echo $lang . " downloaded.\n\r";
                }
            }
        }
    }
}
exec("perl update_mo.pl");
