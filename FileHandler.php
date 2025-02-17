<?php

class FileHandler
{
    private $filePath = "parent_ids.json";
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', '', '', '');
        if ($this->mysqli->connect_errno) {
            error_log("Failed to connect to MySQL: " . $this->mysqli->connect_error);
            exit();
        }
    }

    public function saveFileName($chatId, $fileName)
    {
        $data = $this->getAllData();
        $data[$chatId]['file_name'] = $fileName;
        $this->saveAllData($data);
    }

    public function addMessageId($chatId, $messageId)
    {
        $data = $this->getAllData();
        if (!isset($data[$chatId]['message_ids'])) {
            $data[$chatId]['message_ids'] = [];
        }
        $data[$chatId]['message_ids'][] = $messageId;
        $this->saveAllData($data);
    }

    public function getMessageIds($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['message_ids'] ?? [];
    }

    public function clearMessageIds($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['message_ids'])) {
            unset($data[$chatId]['message_ids']);
        }
        $this->saveAllData($data);
    }


    public function saveAssignmentId($chatId, $assignmentId)
    {
        $data = $this->getAllData();
        $data[$chatId]['assignment_id'] = $assignmentId;
        $this->saveAllData($data);
    }

    public function clearAssignmentId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['assignment_id'])) {
            unset($data[$chatId]['assignment_id']);
            $this->saveAllData($data);
        }
    }

    public function saveNoteTitle($chatId, $noteTitle)
    {
        $data = $this->getAllData();
        $data[$chatId]['note_title'] = $noteTitle;
        $this->saveAllData($data);
    }

    public function getNoteTitle($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['note_title'] ?? null;
    }

    public function clearNoteTitle($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['note_title'])) {
            unset($data[$chatId]['note_title']);
            $this->saveAllData($data);
        }
    }

    public function saveExamTitle($chatId, $examTitle)
    {
        $data = $this->getAllData();
        $data[$chatId]['exam_title'] = $examTitle;
        $this->saveAllData($data);
    }

    public function getExamTitle($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['exam_title'] ?? null;
    }

    public function clearExamTitle($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['exam_title'])) {
            unset($data[$chatId]['exam_title']);
            $this->saveAllData($data);
        }
    }

    public function saveExamDate($chatId, $examDate)
    {
        $data = $this->getAllData();
        $data[$chatId]['exam_date'] = $examDate;
        $this->saveAllData($data);
    }

    public function getExamDate($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['exam_date'] ?? null;
    }

    public function clearExamDate($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['exam_date'])) {
            unset($data[$chatId]['exam_date']);
            $this->saveAllData($data);
        }
    }

    public function getAssignmentId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['assignment_id'] ?? null;
    }


    public function saveClassName($chatId, $className)
    {
        $data = $this->getAllData();
        $data[$chatId]['class']['name'] = $className;
        $this->saveAllData($data);
    }

    public function getClassName($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['class']['name'] ?? null;
    }

    public function getClassTime($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['class']['time'] ?? null;
    }

    public function saveClassPassword($chatId, $classPassword)
    {
        $data = $this->getAllData();
        $data[$chatId]['class']['password'] = $classPassword;
        $this->saveAllData($data);
    }

    public function saveClassTime($chatId, $classTime)
    {
        $data = $this->getAllData();
        $data[$chatId]['class']['time'] = $classTime;
        $this->saveAllData($data);
    }

    public function getClassToken($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['class_token'] ?? null;
    }

    public function saveClassToken($chatId, $token)
    {
        $data = $this->getAllData();
        $data[$chatId]['class_token'] = $token;
        $this->saveAllData($data);
    }

    public function saveStudentName($chatId, $studentName)
    {
        $data = $this->getAllData();
        $data[$chatId]['student_name'] = $studentName;
        $this->saveAllData($data);
    }

    public function saveSelectedClass($chatId, $classId)
    {
        $data = $this->getAllData();
        $data[$chatId]['selected_class'] = $classId;
        $this->saveAllData($data);
    }

    public function getSelectedClass($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['selected_class'] ?? null;
    }

    public function getStudentName($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['student_name'] ?? null;
    }

    public function clearStudentName($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['student_name'])) {
            unset($data[$chatId]['student_name']);
            $this->saveAllData($data);
        }
    }

    public function clearClassToken($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['class_token'])) {
            unset($data[$chatId]['class_token']);
            $this->saveAllData($data);
        }
    }

    public function getUploadId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['upload_id'])) {
            return $data[$chatId]['upload_id'];
        }
        $uploadId = uniqid('upload_', true);
        $data[$chatId]['upload_id'] = $uploadId;
        $this->saveAllData($data);
        return $uploadId;
    }

    public function clearUploadId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['upload_id'])) {
            unset($data[$chatId]['upload_id']);
            $this->saveAllData($data);
        }
    }

    public function finalizeClassData($chatId)
    {
        $data = $this->getAllData();

        $classData = [
            'name' => $data[$chatId]['class']['name'],
            'time' => $data[$chatId]['class']['time'],
            'password' => $data[$chatId]['class']['password']
        ];

        unset($data[$chatId]['class']);
        $this->saveAllData($data);

        return $classData;
    }


    public function getFileName($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['file_name'] ?? null;
    }

    public function clearFileName($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['file_name'])) {
            unset($data[$chatId]['file_name']);
            $this->saveAllData($data);
        }
    }

    public function saveMessageId($chatId, $messageId)
    {
        $data = $this->getAllData();
        $data[$chatId]['message_id'] = $messageId;
        $this->saveAllData($data);
    }

    public function getMessageId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['message_id'] ?? null;
    }

    public function saveChannelId($chatId, $channelId)
    {
        $data = $this->getAllData();
        $data[$chatId]['channel_id'] = $channelId;
        $this->saveAllData($data);
    }

    public function getChannelId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['channel_id'] ?? null;
    }

    public function clearChannelId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['channel_id'])) {
            unset($data[$chatId]['channel_id']);
            $this->saveAllData($data);
        }
    }

    public function clearMessageId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['message_id'])) {
            unset($data[$chatId]['message_id']);
            $this->saveAllData($data);
        }
    }


    public function getFolders($parentId = null, $sectionType = 'academy')
    {
        if ($parentId === null) {
            $stmt = $this->mysqli->prepare("SELECT id, folder_name FROM folders WHERE parent_id IS NULL AND section_type = ?");
            $stmt->bind_param("s", $sectionType);
        } else {
            $stmt = $this->mysqli->prepare("SELECT id, folder_name FROM folders WHERE parent_id = ? AND section_type = ?");
            $stmt->bind_param("is", $parentId, $sectionType);
        }

        if (!$stmt->execute()) {
            error_log("Error executing getFolders query: " . $stmt->error);
            return [];
        }

        $result = $stmt->get_result();
        $folders = [];
        while ($row = $result->fetch_assoc()) {
            $folders[] = $row;
        }
        $stmt->close();
        return $folders;
    }


    public function saveParentId($chatId, $parentId)
    {
        if (!is_numeric($parentId)) {
            $parentId = NULL;
        }

        $data = $this->getAllData();

        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data[$chatId]) || !is_array($data[$chatId])) {
            $data[$chatId] = [];
        }

        $data[$chatId]['parent_id'] = $parentId;
        $this->saveAllData($data);
    }


    public function getParentId($chatId)
    {
        $data = $this->getAllData();
        $parentId = isset($data[$chatId]['parent_id']) ? $data[$chatId]['parent_id'] : NULL;
        if ($parentId !== NULL && !is_numeric($parentId)) {
            error_log("Invalid parent_id: $parentId for chat ID: $chatId");
            return NULL;
        }
        return $parentId;
    }

    public function saveState($chatId, $state)
    {
        $data = $this->getAllData();
        $data[$chatId]['state'] = $state;
        $this->saveAllData($data);
    }

    public function getState($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['state'] ?? NULL;
    }

    private function getAllData()
    {
        if (!file_exists($this->filePath)) {
            error_log("File {$this->filePath} does not exist.");
            return [];
        }
        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return [];
        }
        return $data ?? [];
    }

    private function saveAllData($data)
    {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $file = fopen($this->filePath, 'c+');
        if (flock($file, LOCK_EX)) {
            ftruncate($file, 0);
            fwrite($file, $jsonData);
            fflush($file);
            flock($file, LOCK_UN);
        } else {
            error_log("Could not lock the file {$this->filePath} for writing.");
        }
        fclose($file);
    }

    public function clearParentId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId])) {
            unset($data[$chatId]['parent_id']);
            $this->saveAllData($data);
        }
    }

    public function clearUserData($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId])) {
            unset($data[$chatId]);
            $this->saveAllData($data);
        }
    }

    public function saveButtonName($chatId, $buttonName)
    {
        $data = $this->getAllData();
        $data[$chatId]['button_name'] = $buttonName;
        $this->saveAllData($data);
    }

    public function getButtonName($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['button_name'] ?? NULL;
    }

    public function saveMediaGroupId($chatId, $mediaGroupId)
    {
        $data = $this->getAllData();
        $data[$chatId]['media_group_id'] = $mediaGroupId;
        $this->saveAllData($data);
    }

    public function getMediaGroupId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['media_group_id'] ?? NULL;
    }

    public function clearMediaGroupId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['media_group_id'])) {
            unset($data[$chatId]['media_group_id']);
            $this->saveAllData($data);
        }
    }

    public function getSectionType($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['section_type'] ?? 'academy';
    }

    public function saveSectionType($chatId, $sectionType)
    {
        error_log("Saving section_type as: $sectionType for chat ID: $chatId");
        $data = $this->getAllData();

        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data[$chatId]) || !is_array($data[$chatId])) {
            $data[$chatId] = [];
        }

        $data[$chatId]['section_type'] = $sectionType;
        $this->saveAllData($data);
    }

    public function clearFolderId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['folder_id'])) {
            unset($data[$chatId]['folder_id']);
            $this->saveAllData($data);
        }
    }

    public function saveFolderId($chatId, $folderId)
    {
        $data = $this->getAllData();
        $data[$chatId]['folder_id'] = $folderId;
        $this->saveAllData($data);
    }

    public function getFolderId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['folder_id'] ?? null;
    }

    public function saveChannelData($chatId, $channelData)
    {
        $data = $this->getAllData();
        $data[$chatId]['channel_data'] = $channelData;
        $this->saveAllData($data);
    }

    public function getChannelData($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['channel_data'] ?? null;
    }

    public function clearChannelData($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['channel_data'])) {
            unset($data[$chatId]['channel_data']);
            $this->saveAllData($data);
        }
    }

// در کلاس FileHandler

    public function saveFileId($chatId, $fileId)
    {
        $data = $this->getAllData();
        $data[$chatId]['file_id'] = $fileId;
        $this->saveAllData($data);
    }

    public function getFileId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['file_id'] ?? null;
    }

    public function clearFileId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['file_id'])) {
            unset($data[$chatId]['file_id']);
            $this->saveAllData($data);
        }
    }

    public function saveSettingKey($chatId, $settingKey)
    {
        $data = $this->getAllData();
        $data[$chatId]['setting_key'] = $settingKey;
        $this->saveAllData($data);
    }

    public function getSettingKey($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['setting_key'] ?? null;
    }

    public function clearSettingKey($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['setting_key'])) {
            unset($data[$chatId]['setting_key']);
            $this->saveAllData($data);
        }
    }

    public function saveEditFileName($chatId, $fileName)
    {
        $data = $this->getAllData();
        $data[$chatId]['edit_file_name'] = $fileName;
        $this->saveAllData($data);
    }

    public function getEditFileName($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['edit_file_name'] ?? null;
    }

    public function clearEditFileName($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['edit_file_name'])) {
            unset($data[$chatId]['edit_file_name']);
            $this->saveAllData($data);
        }
    }

    public function saveEditFolderId($chatId, $folderId)
    {
        $data = $this->getAllData();
        $data[$chatId]['edit_folder_id'] = $folderId;
        $this->saveAllData($data);
    }

    public function getEditFolderId($chatId)
    {
        $data = $this->getAllData();
        return $data[$chatId]['edit_folder_id'] ?? null;
    }

    public function clearEditFolderId($chatId)
    {
        $data = $this->getAllData();
        if (isset($data[$chatId]['edit_folder_id'])) {
            unset($data[$chatId]['edit_folder_id']);
            $this->saveAllData($data);
        }
    }

    public function saveFileIdForUpdate($chatId, $fileId)
    {
        $data = $this->getAllData();

        if (!isset($data[$chatId]) || !is_array($data[$chatId])) {
            $data[$chatId] = [];
        }

        $data[$chatId]['file_record_id'] = $fileId;

        $this->saveAllData($data);
    }


    public function getFileIdForUpdate($chatId)
    {
        $data = $this->getAllData();

        return $data[$chatId]['file_record_id'] ?? null;
    }

    public function saveFileCount($chatId, $fileCount)
    {
        $data = $this->getAllData();

        if (!isset($data[$chatId]) || !is_array($data[$chatId])) {
            $data[$chatId] = [];
        }

        $data[$chatId]['file_count'] = $fileCount;

        $this->saveAllData($data);
    }

    public function getFileCount($chatId)
    {
        $data = $this->getAllData();

        return $data[$chatId]['file_count'] ?? null;
    }

    public function clearFileCount($chatId)
    {
        $data = $this->getAllData();

        if (isset($data[$chatId]['file_count'])) {
            unset($data[$chatId]['file_count']);
            $this->saveAllData($data);
        }
    }

}

?>
