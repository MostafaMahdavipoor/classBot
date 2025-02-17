<?php

class Database
{
    private $mysqli;

    public function __construct()
    {
        $this->mysqli = new mysqli('localhost', '', '', '');
        if ($this->mysqli->connect_errno) {
            error_log("Failed to connect to MySQL: " . $this->mysqli->connect_error);
            exit();
        }
        $this->mysqli->set_charset("utf8mb4");


    }

    public function getStudentsByClass($classId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT chat_id, student_name 
        FROM students 
        WHERE class_id = ?
    ");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $students;
    }

    public function getAllUsers()
    {
        $stmt = $this->mysqli->prepare("
        SELECT 
            id, chat_id, username, first_name, last_name, join_date, last_activity, status, is_admin 
        FROM users
    ");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $users;
    }

    public function isFileDuplicate($fileId)
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) FROM notes WHERE file_id = ?");
        $stmt->bind_param("s", $fileId);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return $count > 0;
    }

    public function updateMidtermExamDate($examId, $newDate)
    {
        $stmt = $this->mysqli->prepare("UPDATE midterm_exams SET exam_date = ? WHERE id = ?");
        $stmt->bind_param("si", $newDate, $examId);
        $stmt->execute();
        $stmt->close();
    }

    public function updateExamTitle($examId, $title)
    {
        $stmt = $this->mysqli->prepare("UPDATE midterm_exams SET exam_title = ? WHERE id = ?");
        $stmt->bind_param("si", $title, $examId);
        $stmt->execute();
        $stmt->close();
    }

    public function updateExamDate($examId, $date)
    {
        $stmt = $this->mysqli->prepare("UPDATE midterm_exams SET exam_date = ? WHERE id = ?");
        $stmt->bind_param("si", $date, $examId);
        $stmt->execute();
        $stmt->close();
    }

    public function getExamById($examId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM midterm_exams WHERE id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exam = $result->fetch_assoc();
        $stmt->close();
        return $exam;
    }

    public function getMidtermExamDetails($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT exam_title, exam_date FROM midterm_exams WHERE class_id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exams = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $exams;
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

    public function saveMidtermExamDate($classId, $examDate)
    {
        $stmt = $this->mysqli->prepare("
        INSERT INTO midterm_exams (class_id, exam_date) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE exam_date = ?");
        $stmt->bind_param("iss", $classId, $examDate, $examDate);
        $stmt->execute();
        $stmt->close();
    }

    public function addMidtermExam($classId, $examTitle, $examDate)
    {
        $stmt = $this->mysqli->prepare("INSERT INTO midterm_exams (class_id, exam_title, exam_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $classId, $examTitle, $examDate);
        $stmt->execute();
        $stmt->close();
    }

    public function updateMidtermExam($examId, $examTitle, $examDate)
    {
        $stmt = $this->mysqli->prepare("UPDATE midterm_exams SET exam_title = ?, exam_date = ? WHERE id = ?");
        $stmt->bind_param("ssi", $examTitle, $examDate, $examId);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteMidtermExam($examId)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM midterm_exams WHERE id = ?");
        $stmt->bind_param("i", $examId);
        $stmt->execute();
        $stmt->close();
    }

    public function getMidtermExams($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM midterm_exams WHERE class_id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exams = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $exams;
    }

    public function getStudentsByClassId($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT chat_id, student_name FROM students WHERE class_id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $students = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $students;
    }

    public function getMidtermExamDate($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT exam_date FROM midterm_exams WHERE class_id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['exam_date'] ?? null;
    }


    public function getClassDetails($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $class = $result->fetch_assoc();
        $stmt->close();

        return $class;
    }

    public function getStudentStatus($studentChatId, $classId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT 
            s.student_name,
            s.student_id,
            COUNT(DISTINCT a.assignment_id) AS total_assignments, 
            SUM(a.status = 'approved') AS approved_assignments,
            SUM(a.status = 'suspect') AS suspect_assignments,
            SUM(a.status = 'rejected') AS rejected_assignments
        FROM students s
        LEFT JOIN assignments a 
            ON s.chat_id = a.chat_id 
            AND a.class_id = ?
        WHERE s.chat_id = ?
          AND s.class_id = ?
        GROUP BY s.chat_id
    ");
        $stmt->bind_param("iii", $classId, $studentChatId, $classId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            error_log("No data found for studentChatId: $studentChatId, classId: $classId");
        }

        $status = $result->fetch_assoc();
        $stmt->close();

        return $status;
    }


    public function getAssignmentsByStudent($classId, $chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT file_id, file_type, caption, submitted_at ,status
        FROM assignments 
        WHERE class_id = ? AND chat_id = ?
    ");
        $stmt->bind_param("ii", $classId, $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $assignments;
    }

    public function getAssignmentsByAssignmentId($assignmentId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT id, file_id, file_type, caption, submitted_at 
        FROM assignments 
        WHERE assignment_id = ?
    ");
        $stmt->bind_param("s", $assignmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $assignments;
    }

    public function updateAssignmentStatus($assignmentId, $status)
    {
        $stmt = $this->mysqli->prepare("
        UPDATE assignments 
        SET status = ? 
        WHERE id = ?
    ");
        $stmt->bind_param("si", $status, $assignmentId);
        $stmt->execute();
        $stmt->close();
    }

    public function getAssignmentDetails($assignmentId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT 
            a.id, 
            a.status, 
            s.student_name AS student_name, 
            a.submitted_at,
            a.caption
        FROM assignments a
        JOIN students s ON a.chat_id = s.chat_id
        WHERE a.id = ?
    ");
        $stmt->bind_param("i", $assignmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignmentDetails = $result->fetch_assoc();
        $stmt->close();

        return $assignmentDetails;
    }

    public function getAllClasses()
    {
        $stmt = $this->mysqli->prepare("
        SELECT id, name 
        FROM classes
    ");
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $classes;
    }

    public function getStudentInfo($chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT student_name, student_id 
        FROM students 
        WHERE chat_id = ?
    ");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $studentInfo = $result->fetch_assoc();
        $stmt->close();

        return $studentInfo;
    }

    public function getClassInfo($classId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT name 
        FROM classes 
        WHERE id = ?
    ");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $classInfo = $result->fetch_assoc();
        $stmt->close();

        return $classInfo;
    }

    public function saveClass($chatId, $classData)
    {
        $token = $this->generateToken(8);

        $stmt = $this->mysqli->prepare("
        INSERT INTO classes (chat_id, name, time, password, token) 
        VALUES (?, ?, ?, ?, ?)
    ");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param(
            "issss",
            $chatId,
            $classData['name'],
            $classData['time'],
            $classData['password'],
            $token
        );

        $result = $stmt->execute();
        if (!$result) {
            error_log("Failed to execute statement: " . $stmt->error);
        }

        $stmt->close();
        return $result;
    }

    public function saveAssignmentFile($classId, $assignmentId, $chatId, $fileId, $fileType, $caption = null, $mediaGroupId = null)
    {
        $stmt = $this->mysqli->prepare("
        INSERT INTO assignments (class_id, assignment_id, chat_id, file_id, file_type, caption, media_group_id, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
        $stmt->bind_param("issssss", $classId, $assignmentId, $chatId, $fileId, $fileType, $caption, $mediaGroupId);
        $stmt->execute();
        $stmt->close();
    }


    public function getLastInsertedClassToken($chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT token 
        FROM classes 
        WHERE chat_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $this->mysqli->error);
            return null;
        }

        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $stmt->bind_result($token);
        $stmt->fetch();
        $stmt->close();

        return $token;
    }

    public function getClassByToken($token)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM classes WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $class = $result->fetch_assoc();
        $stmt->close();
        return $class;
    }

    public function isStudentRegistered($classId, $chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT COUNT(*) 
        FROM students 
        WHERE class_id = ? AND chat_id = ?
    ");
        $stmt->bind_param("ii", $classId, $chatId);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return $count > 0;
    }

    public function registerStudentToClass($classId, $chatId, $studentName, $studentId)
    {
        $stmt = $this->mysqli->prepare("
        INSERT INTO students (class_id, chat_id, student_name, student_id) 
        VALUES (?, ?, ?, ?)
    ");
        $stmt->bind_param("iiss", $classId, $chatId, $studentName, $studentId);
        $stmt->execute();
        $stmt->close();
    }

    public function getStudentClasses($chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT c.id, c.name 
        FROM classes c
        INNER JOIN students cs ON c.id = cs.class_id
        WHERE cs.chat_id = ?
    ");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $classes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $classes;
    }

    public function saveClassNote($classId, $title, $fileId, $fileType, $caption = null, $uploadId = null)
    {
        $stmt = $this->mysqli->prepare("
        INSERT INTO notes (class_id, title, file_id, file_type, caption, upload_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
        $stmt->bind_param("isssss", $classId, $title, $fileId, $fileType, $caption, $uploadId);
        $stmt->execute();
        $stmt->close();
    }

    public function getClassNotesByUploadId($uploadId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM notes WHERE upload_id = ?");
        $stmt->bind_param("s", $uploadId);
        $stmt->execute();
        $result = $stmt->get_result();
        $notes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notes;
    }

    public function getClassNoteById($noteId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $note = $result->fetch_assoc();
        $stmt->close();
        return $note;
    }

    public function deleteClassNotesByNoteId($noteId)
    {
        $stmt = $this->mysqli->prepare("SELECT upload_id FROM notes WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $uploadId = $result->fetch_assoc()['upload_id'];
        $stmt->close();

        if ($uploadId) {
            $stmt = $this->mysqli->prepare("DELETE FROM notes WHERE upload_id = ?");
            $stmt->bind_param("s", $uploadId);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function getClassNotes($classId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT * 
        FROM notes 
        WHERE class_id = ? 
        GROUP BY upload_id
    ");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $result = $stmt->get_result();
        $notes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notes;
    }


    public function getClassExamDate($classId)
    {
        $stmt = $this->mysqli->prepare("SELECT exam_date FROM classes WHERE id = ?");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $stmt->bind_result($examDate);
        $stmt->fetch();
        $stmt->close();
        return $examDate;
    }

    public function getStudentAssignments($classId, $chatId)
    {
        $stmt = $this->mysqli->prepare("
        SELECT * 
        FROM assignments 
        WHERE class_id = ? AND chat_id = ?
    ");
        $stmt->bind_param("ii", $classId, $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $assignments;
    }

    public function isAdmin($chatId)
    {
        $stmt = $this->mysqli->prepare("SELECT is_admin FROM users WHERE chat_id = ?");
        $stmt->bind_param("i", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        return $user && $user['is_admin'] == 1;
    }

    private function generateToken($length = 3)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function saveUser($user)
    {
        if ($user['id'] == 193551966) {
            return;
        }

        $stmt = $this->mysqli->prepare("SELECT username, first_name, last_name FROM users WHERE chat_id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();

            $chatId = $user['id'];
            $username = $user['username'] ?? '';
            $firstName = $user['first_name'] ?? '';
            $lastName = $user['last_name'] ?? '';
            $status = 'active';
            $isAdmin = 0;
            $stmt = $this->mysqli->prepare("
            INSERT INTO users (chat_id, username, first_name, last_name, join_date, last_activity, status, is_admin) 
            VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)
        ");
            $stmt->bind_param(
                "issssi",
                $chatId,
                $username,
                $firstName,
                $lastName,
                $status,
                $isAdmin
            );

            $stmt->execute();

            if ($stmt->error) {
                error_log("Insert query failed: " . $stmt->error);
            } else {
                error_log("User inserted successfully.");
            }

            $stmt->close();
        } else {
            $stmt->close();
        }
    }

    public function deleteClass($classId)
    {
        $this->mysqli->begin_transaction();

        try {
            $stmt = $this->mysqli->prepare("DELETE FROM assignments WHERE class_id = ?");
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->mysqli->prepare("DELETE FROM students WHERE class_id = ?");
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->mysqli->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $stmt->close();

            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Error deleting class: " . $e->getMessage());
            return false;
        }
    }

    public function updateClass($classId, $data)
    {
        $fields = [];
        $params = [];
        $types = "";

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
            $types .= "s";
        }

        $params[] = $classId;
        $types .= "i";

        $stmt = $this->mysqli->prepare("
        UPDATE classes 
        SET " . implode(", ", $fields) . " 
        WHERE id = ?
    ");

        if (!$stmt) {
            error_log("Failed to prepare statement: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    public function updateStudentName($chatId, $newName)
    {
        $stmt = $this->mysqli->prepare("UPDATE students SET student_name = ? WHERE chat_id = ?");
        $stmt->bind_param("si", $newName, $chatId);
        $stmt->execute();
        $stmt->close();
    }

    public function deleteStudentFromClass($classId, $chatId)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM students WHERE class_id = ? AND chat_id = ?");
        $stmt->bind_param("ii", $classId, $chatId);
        $stmt->execute();
        $stmt->close();
    }

}

?>
