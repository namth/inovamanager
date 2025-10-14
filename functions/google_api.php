<?php
use Google\Client as Google_Client;
use Google\Service\Drive as Google_Service_Drive;
use Google\Service\Drive\DriveFile as Google_Service_Drive_File;
use Google\Service\Docs as Google_Service_Docs;
use Google\Service\Docs\SubstringMatchCriteria as Google_Service_SubstringMatchCriteria;
use Google\Service\Docs\Request as Google_Service_Docs_Request;
use Google\Service\Docs\BatchUpdateDocumentRequest as Google_Service_Docs_BatchUpdateDocumentRequest;

function google_docs_get_content($fileId)
{
    global $client; // Giả sử bạn đã khai báo biến $client để kết nối Google API

    $service = new Google_Service_Docs($client);

    // Lấy nội dung file
    $response = $service->documents->get($fileId);

    // Trích xuất nội dung từ response
    $allText = [];
    foreach ($response->getBody()->getContent() as $structuralElement) {
        if ($structuralElement->paragraph) {
            foreach ($structuralElement->paragraph->elements as $paragraphElement) {
                if ($paragraphElement->textRun) {
                    $allText[] = $paragraphElement->textRun->content;
                }
            }
        }
    }

    return $allText;
}

function google_docs_edit_content($fileId, $newContent)
{
    global $client; // Giả sử bạn đã khai báo biến $client để kết nối Google API

    $service = new Google_Service_Docs($client);

    // Chuẩn bị nội dung mới
    $requests = [
        [
            'insertText' => [
                'location' => [
                    'index' => 1, // Chèn từ đầu file
                ],
                'text' => $newContent, // Nội dung mới
            ],
        ],
    ];

    // Gửi yêu cầu sửa đổi
    $result = $service->documents->batchUpdate($fileId, [
        'requests' => $requests,
    ]);

    return $result;
}

function google_clone_file($sourceFileId, Google_Service_Drive_File $new_file, $optParams = [])
{
    global $client;

    $service = new Google_Service_Drive($client);

    try {
        // Duplicate the file
        $new_file->setName($optParams['newfilename']);
        // Move the new file to specific folder
        $new_file->setParents(array($optParams['folderId']));
        $copiedFile = $service->files->copy($sourceFileId, $new_file);

        return $copiedFile->id;
    } catch (Exception $e) {
        return false;
    }
}


function google_docs_replaceText($documentId, $replacements)
{
    global $client;

    $service = new Google_Service_Docs($client);

    // Duyệt qua các cặp khóa-giá trị
    foreach ($replacements as $key => $value) {
        $e = new Google_Service_SubstringMatchCriteria();
        $e->text = $key;
        $e->setMatchCase(false);


        $requests[] = new Google_Service_Docs_Request(array(
            'replaceAllText' => array(
                'replaceText' => $value,
                'containsText' => $e
            ),
        ));
    }

    $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
        'requests' => $requests
    ));

    $response = $service->documents->batchUpdate($documentId, $batchUpdateRequest);
    return $response->id;
}

/* 
 * Hàm chèn hình ảnh vào Google Docs
 */
function insertImageIntoGoogleDoc($fileId, $imageUrl, $textToReplace = '{your_image}')
{
    global $client; // Biến $client được định nghĩa ở bước xác thực
    $found = false;
    $service = new Google_Service_Docs($client);

    // 1. Lấy nội dung tài liệu
    $document = $service->documents->get($fileId);

    // 2. Tìm vị trí của văn bản cần thay thế
    $startIndex = null;
    foreach ($document->getBody()->getContent() as $structuralElement) {
        if ($structuralElement->paragraph) {
            foreach ($structuralElement->paragraph->elements as $paragraphElement) {
                if ($paragraphElement->textRun) {
                    $text = $paragraphElement->textRun->content;
                    if (strpos($text, $textToReplace) !== false) {
                        $startIndex = $paragraphElement->startIndex;
                        $found = true;

                        // 4. Xóa văn bản cần thay thế ( nếu tìm thấy)
                        $requests[] = new Google_Service_Docs_Request(array(
                            'deleteContentRange' => [
                                'range' => [
                                    'startIndex' => $startIndex,
                                    'endIndex' => $startIndex + strlen($textToReplace),
                                ],
                            ],
                        ));

                        // 5. Chèn hình ảnh
                        $requests[] = new Google_Service_Docs_Request(array(
                            'insertInlineImage' => array(
                                'uri' => $imageUrl,
                                'location' => array(
                                    'index' => $startIndex,
                                )
                            )
                        ));
                    }
                }
            }
        } else if ($structuralElement->table) {
            foreach ($structuralElement->table->tableRows as $row) {
                foreach ($row->tableCells as $cell) {
                    foreach ($cell->content as $content) {
                        if ($content->paragraph) {
                            foreach ($content->paragraph->elements as $paragraphElement) {
                                if ($paragraphElement->textRun) {
                                    $text = $paragraphElement->textRun->content;
                                    if (strpos($text, $textToReplace) !== false) {
                                        $startIndex = $paragraphElement->startIndex;
                                        $found = true;

                                        // 4. Xóa văn bản cần thay thế ( nếu tìm thấy)
                                        $requests[] = new Google_Service_Docs_Request(array(
                                            'deleteContentRange' => [
                                                'range' => [
                                                    'startIndex' => $startIndex,
                                                    'endIndex' => $startIndex + strlen($textToReplace),
                                                ],
                                            ],
                                        ));

                                        // 5. Chèn hình ảnh
                                        $requests[] = new Google_Service_Docs_Request(array(
                                            'insertInlineImage' => array(
                                                'uri' => $imageUrl,
                                                'location' => array(
                                                    'index' => $startIndex,
                                                )
                                            )
                                        ));
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // 3. Xử lý trường hợp không tìm thấy văn bản
    if ($startIndex === null) {
        return ['success' => false, 'message' => 'Văn bản cần thay thế không được tìm thấy'];
    }

    if ($found) {
        // 6. Gửi yêu cầu cập nhật
        $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
            'requests' => $requests
        ));

        $result = $service->documents->batchUpdate($fileId, $batchUpdateRequest);
    }



    return ['success' => true, 'message' => 'Hình ảnh đã được chèn'];
}