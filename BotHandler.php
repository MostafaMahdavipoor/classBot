<?php
require_once 'jdf.php';

class BotHandler
{
    private $chatId;
    private $text;
    private $messageId;
    private $message;
    public $db;
    private $fileHandler;

    public function __construct($chatId, $text, $messageId, $message)
    {
        $this->chatId = $chatId;
        $this->text = $text;
        $this->messageId = $messageId;
        $this->message = $message;
        $this->db = new Database();
        $this->fileHandler = new FileHandler();
    }

    public function sendRequest($method, $data)
    {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/$method";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            $errorResponse = json_decode($response, true);
            $errorMessage = $errorResponse['description'] ?? 'Unknown error';
            return false;
        }


    }


    public function deleteMessageWithDelay($delay = 3)
    {
        $delayInMicroseconds = $delay * 1000000;
        $this->sendRequest("deleteMessage", [
            "chat_id" => $this->chatId,
            "message_id" => $this->messageId
        ]);
    }


    public function handlePreCheckoutQuery($update)
    {
        $queryId = $update['pre_checkout_query']['id'];
        $this->sendRequest("answerPreCheckoutQuery", [
            "pre_checkout_query_id" => $queryId,
            "ok" => true
        ]);
    }

    public function showMainMenu($isAdmin = false, $messageId = null)
    {
        if ($isAdmin) {
            $menuItems = [
                [["text" => "⚙️ مدیریت کلاس ها", "callback_data" => "bot_settings"]],
                [["text" => "📚 ایجاد کلاس", "callback_data" => "create_class"]],
                [["text" => "📢 اطلاع رسانی", "callback_data" => "notify_admin"]]
            ];
        } else {
            $menuItems = [
                [["text" => "👨‍🏫 راه ارتباطی با استاد", "callback_data" => "contact_teacher"]],
                [["text" => "📚 کلاس‌های من", "callback_data" => "my_classes"]]
            ];
        }

        $params = [
            "chat_id" => $this->chatId,
            "text" => "لطفاً یکی از گزینه‌های زیر را انتخاب کنید:",
            "reply_markup" => json_encode([
                "inline_keyboard" => $menuItems
            ]),
        ];

        if ($messageId) {
            $params["message_id"] = $messageId;
            $this->sendRequest("editMessageText", $params);
        } else {
            $this->sendRequest("sendMessage", $params);
        }
    }


    private function cancelButton()
    {
        return [
            "text" => "❌ انصراف",
            "callback_data" => "cancel_action"
        ];
    }


    public function handleCallbackQuery($callbackQuery)
    {
        $callbackData = $callbackQuery["data"] ?? null;
        $chatId = $callbackQuery["message"]["chat"]["id"] ?? null;
        $callbackQueryId = $callbackQuery["id"] ?? null;
        $messageId = $callbackQuery["message"]["message_id"] ?? null;
        $currentKeyboard = $callbackQuery["message"]["reply_markup"]["inline_keyboard"] ?? [];


        $this->sendRequest("answerCallbackQuery", [
            "callback_query_id" => $callbackQueryId,
            "text" => "در حال پردازش...",
            "show_alert" => false
        ]);


        if ($callbackData === "bot_settings") {
            $classes = $this->db->getAllClasses();

            $inlineKeyboard = [];
            if (empty($classes)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $chatId,
                    "message_id" => $messageId,
                    "text" => "📚 هنوز هیچ کلاسی ایجاد نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "main_menu"]]
                        ]
                    ])
                ]);
            } else {
                foreach ($classes as $class) {
                    $inlineKeyboard[] = [
                        ["text" => $class['name'], "callback_data" => "manage_class_" . $class['id']],
                        ["text" => "✏️ ویرایش", "callback_data" => "edit_class_" . $class['id']],
                        ["text" => "❌ حذف", "callback_data" => "delete_class_" . $class['id']]
                    ];
                }


                $inlineKeyboard[] = [["text" => "🔙 بازگشت", "callback_data" => "main_menu"]];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $chatId,
                    "message_id" => $messageId,
                    "text" => "📋 لیست تمام کلاس‌ها:",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "delete_class_") === 0) {
            $classId = str_replace("delete_class_", "", $callbackData);

            $class = $this->db->getClassDetails($classId);

            if (!$class) {
                $this->sendRequest("answerCallbackQuery", [
                    "callback_query_id" => $callbackQueryId,
                    "text" => "❌ کلاس موردنظر یافت نشد.",
                    "show_alert" => true
                ]);
                return;
            }

            $className = $class['name'] ?? "نامشخص";

            $inlineKeyboard = [
                [["text" => "✅ بله، حذف کن", "callback_data" => "confirm_delete_class_" . $classId]],
                [["text" => "🔙 خیر، بازگشت", "callback_data" => "bot_settings"]]
            ];

            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "❓ آیا مطمئن هستید که می‌خواهید کلاس «{$className}» را حذف کنید؟",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif ($callbackData === "go_to_main_menu") {
            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);
            return;
        } elseif (strpos($callbackData, "confirm_delete_class_") === 0) {
            $classId = str_replace("confirm_delete_class_", "", $callbackData);

            $class = $this->db->getClassDetails($classId);

            if (!$class) {
                $this->sendRequest("answerCallbackQuery", [
                    "callback_query_id" => $callbackQueryId,
                    "text" => "❌ کلاس موردنظر یافت نشد.",
                    "show_alert" => true
                ]);
                return;
            }

            $deleteSuccess = $this->db->deleteClass($classId);

            if ($deleteSuccess) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $chatId,
                    "message_id" => $messageId,
                    "text" => "✅ کلاس با موفقیت حذف شد.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "bot_settings"]]
                        ]
                    ])
                ]);
            } else {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $chatId,
                    "message_id" => $messageId,
                    "text" => "⚠️ خطا در حذف کلاس. لطفاً دوباره تلاش کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "bot_settings"]]
                        ]
                    ])
                ]);
            }

            return;
        } elseif (strpos($callbackData, "edit_class_name_") === 0) {
            $classId = str_replace("edit_class_name_", "", $callbackData);

            $this->fileHandler->saveState($chatId, "editing_class_name_$classId");

            $inlineKeyboard = [
                [["text" => "🔙 بازگشت", "callback_data" => "edit_class_" . $classId]]
            ];

            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "لطفاً نام جدید کلاس را وارد کنید:",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif (strpos($callbackData, "edit_class_time_") === 0) {
            $classId = str_replace("edit_class_time_", "", $callbackData);
            $this->fileHandler->saveMessageId($chatId, $messageId);

            $this->fileHandler->saveState($chatId, "editing_class_time_$classId");
            $inlineKeyboard = [
                [["text" => "🔙 بازگشت", "callback_data" => "edit_class_" . $classId]]
            ];
            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "لطفاً زمان جدید کلاس را وارد کنید:",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif (strpos($callbackData, "add_note_") === 0) {
            $classId = str_replace("add_note_", "", $callbackData);
            $this->fileHandler->saveState($this->chatId, "adding_note_" . $classId);
            $this->fileHandler->saveMessageId($chatId, $messageId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $messageId,
                "text" => "📄 لطفاً عنوان جزوه را وارد کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "class_notes_"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "edit_class_password_") === 0) {
            $classId = str_replace("edit_class_password_", "", $callbackData);
            $this->fileHandler->saveMessageId($chatId, $messageId);

            $this->fileHandler->saveState($chatId, "editing_class_password_$classId");
            $inlineKeyboard = [
                [["text" => "🔙 بازگشت", "callback_data" => "edit_class_" . $classId]]
            ];
            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "لطفاً رمز جدید کلاس را وارد کنید:",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif ($callbackData === "main_menu") {
            $this->showMainMenu($this->db->isAdmin($this->chatId), $messageId);
            return;
        } elseif (strpos($callbackData, "manage_class_") === 0) {
            $classId = str_replace("manage_class_", "", $callbackData);

            $this->fileHandler->saveSelectedClass($this->chatId, $classId);

            $inlineKeyboard = [
                [["text" => "📄 جزوه", "callback_data" => "class_notes_" . $classId]],
                [["text" => "👥 دانشجویان", "callback_data" => "class_students_" . $classId]],
                [["text" => "📅 امتحان میان‌ترم", "callback_data" => "class_exam_" . $classId]],
                [["text" => "📢 اطلاع‌رسانی", "callback_data" => "class_announcement_" . $classId]],
                [["text" => "🔙 بازگشت", "callback_data" => "bot_settings"]]
            ];

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $callbackQuery["message"]["message_id"],
                "text" => "📚 مدیریت کلاس انتخابی:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => $inlineKeyboard
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "delete_exam_") === 0) {
            $examId = str_replace("delete_exam_", "", $callbackData);

            $this->db->deleteMidtermExam($examId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ امتحان با موفقیت حذف شد.",
            ]);

            return;
        } elseif (strpos($callbackData, "add_exam_") === 0) {
            $classId = str_replace("add_exam_", "", $callbackData);

            $this->fileHandler->saveState($this->chatId, "adding_exam");
            $this->fileHandler->saveSelectedClass($this->chatId, $classId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "➕ شما در حال اضافه کردن یک امتحان جدید هستید.\n\nلطفاً عنوان امتحان را وارد کنید (مثلاً: «میان‌ترم اول» یا «میان‌ترم دوم»).",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);

            return;
        } elseif (strpos($callbackData, "edit_exam_") === 0) {
            $examId = str_replace("edit_exam_", "", $callbackData);

            $examDetails = $this->db->getExamById($examId);

            if (!$examDetails) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ امتحان مورد نظر یافت نشد.",
                ]);
                return;
            }

            $this->fileHandler->saveState($this->chatId, "editing_exam_" . $examId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🛠️ *چه بخشی از امتحان را می‌خواهید ویرایش کنید؟*\n\n" .
                    "📘 *عنوان فعلی:* " . $examDetails['exam_title'] . "\n" .
                    "📅 *تاریخ فعلی:* " . $examDetails['exam_date'],
                "parse_mode" => "Markdown",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "✏️ ویرایش عنوان", "callback_data" => "exam_edit_title_" . $examId]],
                        [["text" => "📅 ویرایش تاریخ", "callback_data" => "exam_edit_date_" . $examId]],
                        [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "exam_edit_date_") === 0) {
            $classId = str_replace("exam_edit_date_", "", $callbackData);

            $this->fileHandler->saveState($this->chatId, "editing_exam_date_" . $examId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "📅 لطفاً تاریخ امتحان میان‌ترم را وارد کنید (می‌توانید متن فارسی یا تاریخ دلخواه وارد کنید):",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);

            return;
        } elseif (strpos($callbackData, "class_announcement_") === 0) {
            $classId = str_replace("class_announcement_", "", $callbackData);

            $this->fileHandler->saveState($this->chatId, "sending_announcement_" . $classId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $messageId,
                "text" => "📢 لطفاً پیام اطلاع‌رسانی خود را وارد کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ لغو عملیات", "callback_data" => "main_menu"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "notify_admin") === 0) {
            $this->fileHandler->saveState($this->chatId, "sending_global_announcement");
            $this->fileHandler->saveMessageId($this->chatId, $messageId);


            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $messageId,
                "text" => "📢 لطفاً پیام اطلاع‌رسانی خود را وارد کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "exam_edit_title_") === 0) {
            $examId = str_replace("exam_edit_title_", "", $callbackData);

            $this->fileHandler->saveState($this->chatId, "editing_exam_title_" . $examId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✏️ لطفاً عنوان جدید امتحان را وارد کنید:",
                "parse_mode" => "Markdown",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "class_exam_") === 0) {
            $classId = str_replace("class_exam_", "", $callbackData);

            $exams = $this->db->getMidtermExams($classId);

            if (empty($exams)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "⚠️ هنوز امتحانی برای این کلاس ثبت نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "➕ افزودن امتحان جدید", "callback_data" => "add_exam_" . $classId]],
                            [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                foreach ($exams as $exam) {
                    $this->sendRequest("sendMessage", [
                        "chat_id" => $this->chatId,
                        "text" => "📅 *عنوان امتحان:* " . $exam['exam_title'] . "\n" .
                            "📅 *تاریخ امتحان:* " . $exam['exam_date'],
                        "parse_mode" => "Markdown",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "✏️ ویرایش", "callback_data" => "edit_exam_" . $exam['id']]],
                                [["text" => "❌ حذف", "callback_data" => "delete_exam_" . $exam['id']]]
                            ]
                        ])
                    ]);
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚙️ مدیریت امتحانات:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "➕ افزودن امتحان جدید", "callback_data" => "add_exam_" . $classId]],
                            [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]]
                        ]
                    ])
                ]);
            }

            return;
        } elseif (strpos($callbackData, "class_notes_") === 0) {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $notes = $this->db->getClassNotes($classId);

            if (empty($notes)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "📄 هنوز جزوه‌ای برای این کلاس ثبت نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "➕ افزودن جزوه", "callback_data" => "add_note_" . $classId]],
                            [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($notes as $note) {
                    $inlineKeyboard[] = [
                        ["text" => $note['title'], "callback_data" => "view_note_" . $note['id']],
                        ["text" => "❌ حذف", "callback_data" => "delete_note_" . $note['id']]
                    ];
                }

                $inlineKeyboard[] = [["text" => "➕ افزودن جزوه", "callback_data" => "add_note_" . $classId]];
                $inlineKeyboard[] = [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "📄 لیست جزوات کلاس:",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "delete_note_") === 0) {
            $noteId = str_replace("delete_note_", "", $callbackData);
            $this->db->deleteClassNotesByNoteId($noteId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $messageId,
                "text" => "✅ جزوه با موفقیت حذف شد."
            ]);
            sleep(2);
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $notes = $this->db->getClassNotes($classId);

            if (empty($notes)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "📄 هنوز جزوه‌ای برای این کلاس ثبت نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "➕ افزودن جزوه", "callback_data" => "add_note_" . $classId]],
                            [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($notes as $note) {
                    $inlineKeyboard[] = [
                        ["text" => $note['title'], "callback_data" => "view_note_" . $note['id']],
                        ["text" => "❌ حذف", "callback_data" => "delete_note_" . $note['id']]
                    ];
                }

                $inlineKeyboard[] = [["text" => "➕ افزودن جزوه", "callback_data" => "add_note_" . $classId]];
                $inlineKeyboard[] = [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "📄 لیست جزوات کلاس:",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "view_note_") === 0) {
            $noteId = str_replace("view_note_", "", $callbackData);
            $note = $this->db->getClassNoteById($noteId);

            if ($note) {
                $uploadId = $note['upload_id'];
                $notes = $this->db->getClassNotesByUploadId($uploadId);

                foreach ($notes as $relatedNote) {
                    if ($relatedNote['file_type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id" => $this->chatId,
                            "photo" => $relatedNote['file_id'],
                            "caption" => "📄 " . $relatedNote['title']
                        ]);
                    } elseif ($relatedNote['file_type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id" => $this->chatId,
                            "document" => $relatedNote['file_id'],
                            "caption" => "📄 " . $relatedNote['title']
                        ]);
                    }
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "🔙 برای بازگشت به لیست جزوات کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "class_notes_"]]
                        ]
                    ])
                ]);
            } else {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ جزوه یافت نشد.",
                ]);
            }
            return;
        } elseif (strpos($callbackData, "edit_class_link_") === 0) {
            $classId = str_replace("edit_class_link_", "", $callbackData);
            $this->fileHandler->saveMessageId($chatId, $messageId);

            $this->fileHandler->saveState($chatId, "editing_class_link_$classId");
            $inlineKeyboard = [
                [["text" => "🔙 بازگشت", "callback_data" => "edit_class_" . $classId]]
            ];
            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "لطفاً لینک جدید کلاس را وارد کنید:",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif (strpos($callbackData, "edit_class_") === 0) {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);

            $class = $this->db->getClassDetails($classId);

            if (!$class) {
                $this->sendRequest("answerCallbackQuery", [
                    "callback_query_id" => $callbackQueryId,
                    "text" => "❌ کلاس موردنظر یافت نشد.",
                    "show_alert" => true
                ]);
                return;
            }

            $className = $class['name'] ?? "نامشخص";
            $classTime = $class['time'] ?? "نامشخص";
            $classPassword = $class['password'] ?? "بدون رمز";
            $classToken = $class['token'] ?? "نامشخص";

            $botUsername = "coursesdelavarian_bot";
            $classLink = "https://t.me/$botUsername?start=" . htmlspecialchars($classToken);

            $message = "✏️ <b>ویرایش اطلاعات کلاس:</b>\n\n" .
                "📚 <b>نام کلاس:</b> $className\n" .
                "⏰ <b>زمان:</b> $classTime\n" .
                "🔑 <b>رمز:</b> $classPassword\n" .
                "🌐 <b>لینک کلاس:</b>\n<code>$classLink</code>\n\n" .
                "لطفاً یکی از موارد زیر را برای ویرایش انتخاب کنید.";

            $inlineKeyboard = [
                [["text" => "📚 نام کلاس", "callback_data" => "edit_class_name_" . $classId]],
                [["text" => "⏰ زمان کلاس", "callback_data" => "edit_class_time_" . $classId]],
                [["text" => "🔑 رمز کلاس", "callback_data" => "edit_class_password_" . $classId]],
                [["text" => "🌐 تغییر لینک", "callback_data" => "edit_class_link_" . $classId]],
                [["text" => "🔙 بازگشت", "callback_data" => "bot_settings"]]
            ];

            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => $message,
                "parse_mode" => "HTML",
                "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
            ]);

            return;
        } elseif (strpos($callbackData, "edit_student_") === 0) {
            $data = explode("_", $callbackData);
            $classId = $data[2];
            $studentChatId = $data[3];

            $student = $this->db->getStudentInfo($studentChatId);

            if (!$student) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ دانشجوی مورد نظر یافت نشد."
                ]);
                return;
            }

            $this->fileHandler->saveState($this->chatId, "editing_student_" . $studentChatId . "_" . $classId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✏️ لطفاً نام جدید برای دانشجوی «" . htmlspecialchars($student['student_name']) . "» را وارد کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "delete_student_") === 0) {
            $data = explode("_", $callbackData);
            $classId = $data[2];
            $studentChatId = $data[3];

            $student = $this->db->getStudentInfo($studentChatId);

            if (!$student) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ دانشجوی مورد نظر یافت نشد."
                ]);
                return;
            }

            $this->db->deleteStudentFromClass($classId, $studentChatId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ دانشجوی «" . htmlspecialchars($student['student_name']) . "» با موفقیت از کلاس حذف شد."
            ]);

            $this->sendRequest("editMessageReplyMarkup", [
                "chat_id" => $this->chatId,
                "message_id" => $callbackQuery["message"]["message_id"],
                "reply_markup" => json_encode(["inline_keyboard" => []])
            ]);

            return;
        } elseif (strpos($callbackData, "class_students_") === 0) {
            $classId = str_replace("class_students_", "", $callbackData);
            $students = $this->db->getStudentsByClass($classId);

            if (empty($students)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => "👥 هنوز دانشجویی در این کلاس ثبت‌نام نکرده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($students as $student) {
                    $inlineKeyboard[] = [
                        ["text" => $student['student_name'], "callback_data" => "view_student_" . $student['chat_id'] . "_" . $classId],
                        ["text" => "✏️ ویرایش", "callback_data" => "edit_student_" . $classId . "_" . $student['chat_id']],
                        ["text" => "❌ حذف", "callback_data" => "delete_student_" . $classId . "_" . $student['chat_id']]
                    ];
                }

                $inlineKeyboard[] = [["text" => "🔙 بازگشت", "callback_data" => "manage_class_" . $classId]];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => "👥 لیست دانشجویان کلاس:",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "view_student_") === 0) {
            $parts = explode("_", $callbackData);

            if (count($parts) === 4 && $parts[0] === "view" && $parts[1] === "student") {
                $studentChatId = $parts[2];
                $classId = $parts[3];
            } else {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ داده‌های نامعتبر. لطفاً دوباره تلاش کنید."
                ]);
                return;
            }
            $studentDetails = $this->db->getStudentStatus($studentChatId, $classId);

            if (!$studentDetails) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => "⚠️ اطلاعات دانشجو یافت نشد."
                ]);
            } else {
                $studentName = $studentDetails['student_name'] ?? 'نامشخص';
                $studentId = $studentDetails['student_id'] ?? 'نامشخص';
                $totalAssignments = $studentDetails['total_assignments'] ?? 0;
                $approvedAssignments = $studentDetails['approved_assignments'] ?? 0;
                $suspectAssignments = $studentDetails['suspect_assignments'] ?? 0;
                $rejectedAssignments = $studentDetails['rejected_assignments'] ?? 0;

                $message = "👤 <b>نام:</b> $studentName\n" .
                    "🎓 <b>شماره دانشجویی:</b> $studentId\n" .
                    "📄 <b>تعداد تمرین‌ها:</b> $totalAssignments\n" .
                    "✅ <b>تایید شده:</b> $approvedAssignments\n" .
                    "⚠️ <b>مشکوک به تقلب:</b> $suspectAssignments\n" .
                    "❌ <b>تقلب:</b> $rejectedAssignments";

                $inlineKeyboard = [
                    [["text" => "📤 مشاهده تمرین‌ها", "callback_data" => "view_assignments_" . $classId . "_" . $studentChatId]],
                    [["text" => "🔙 بازگشت", "callback_data" => "class_students_" . $classId]]
                ];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => $message,
                    "parse_mode" => "HTML",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);


            }
            return;
        } elseif (strpos($callbackData, "view_assignments_") === 0) {
            $data = str_replace("view_assignments_", "", $callbackData);
            list($classId, $studentChatId) = explode("_", $data);

            $assignments = $this->db->getAssignmentsByStudent($classId, $studentChatId);

            if (empty($assignments)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => "📁 این دانشجو هنوز تمرینی ارسال نکرده است."
                ]);
            } else {
                require_once 'jdf.php';

                foreach ($assignments as $assignment) {
                    $caption = $assignment['caption'] ?? 'بدون توضیحات';
                    $submittedAt = jdate('Y/m/d H:i', strtotime($assignment['submitted_at']));
                    $status = $assignment['status'] ?? 'نامشخص';

                    switch ($status) {
                        case 'approved':
                            $statusText = '✅ تأیید شده';
                            break;
                        case 'suspect':
                            $statusText = '⚠️ مشکوک به تقلب';
                            break;
                        case 'rejected':
                            $statusText = '❌ تقلب';
                            break;
                        default:
                            $statusText = '⏳ در انتظار بررسی';
                    }

                    $messageCaption = "📅 تاریخ ارسال: $submittedAt\n" .
                        "✏️ توضیحات: $caption\n" .
                        "📌 وضعیت: $statusText";

                    if ($assignment['file_type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id" => $this->chatId,
                            "photo" => $assignment['file_id'],
                            "caption" => $messageCaption,
                        ]);
                    } elseif ($assignment['file_type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id" => $this->chatId,
                            "document" => $assignment['file_id'],
                            "caption" => $messageCaption,
                        ]);
                    }
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📋 برای بازگشت به اطلاعات دانشجو روی دکمه زیر کلیک کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "view_student_" . $studentChatId . "_" . $classId]]
                        ]
                    ])
                ]);
            }
            return;
        } elseif ($callbackData === "contact_teacher") {
            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $this->message['message_id'],
                "text" => "👨‍🏫 *راه ارتباطی با استاد*\n\n" .
                    "📧 *ایمیل:* `Delavarian@gmail.com`\n\n" .
                    "📢 *کانال تلگرام:* [@coursesdelavarian](https://t.me/coursesdelavarian)\n\n",
                "parse_mode" => "Markdown",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت", "callback_data" => "main_menu"]]
                    ]
                ])
            ]);
            return;
        } elseif ($callbackData === "my_classes") {
            $classes = $this->db->getStudentClasses($this->chatId);

            if (empty($classes)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ شما هنوز در هیچ کلاسی ثبت‌نام نکرده‌اید."
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($classes as $class) {
                    $inlineKeyboard[] = [
                        ["text" => $class['name'], "callback_data" => "class_" . $class['id']]
                    ];
                }

                $inlineKeyboard[] = [
                    ["text" => "⬅️ بازگشت", "callback_data" => "main_menu"]
                ];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $callbackQuery["message"]["message_id"],
                    "text" => "کلاس‌های شما:",
                    "parse_mode" => "HTML",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "class_") === 0) {
            $classId = str_replace("class_", "", $callbackData);

            $this->fileHandler->saveSelectedClass($this->chatId, $classId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $this->message['message_id'],
                "text" => "📚 لطفاً یکی از گزینه‌های زیر را برای کلاس انتخاب کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "📄 جزوات", "callback_data" => "student_class_notes"]],
                        [["text" => "📅 تاریخ امتحان میان‌ترم", "callback_data" => "student_class_exam_date"]],
                        [["text" => "📤 ارسال تمرین", "callback_data" => "submit_assignment"]],
                        [["text" => "📁 تمرین‌های من", "callback_data" => "my_assignments"]],
                        [["text" => "🔙 بازگشت", "callback_data" => "my_classes"]]
                    ]
                ])
            ]);
            return;
        } elseif ($callbackData === "student_class_exam_date") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $examDetails = $this->db->getMidtermExamDetails($classId);

            if (empty($examDetails)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->message['message_id'],
                    "text" => "📅 هنوز تاریخ امتحان میان‌ترم برای این کلاس ثبت نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                $examText = "📅 *اطلاعات امتحانات میان‌ترم:*\n\n";

                foreach ($examDetails as $exam) {
                    $examText .= "📘 *عنوان امتحان:* " . $exam['exam_title'] . "\n" .
                        "📅 *تاریخ:* " . $exam['exam_date'] . "\n\n";
                }

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->message['message_id'],
                    "text" => $examText,
                    "parse_mode" => "Markdown",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "class_" . $classId]]
                        ]
                    ])
                ]);
            }
            return;
        } elseif ($callbackData === "student_class_notes") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $notes = $this->db->getClassNotes($classId);

            if (empty($notes)) {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->message['message_id'],
                    "text" => "📄 هنوز جزوه‌ای برای این کلاس ثبت نشده است.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "class_" . $classId]]
                        ]
                    ])
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($notes as $note) {
                    $inlineKeyboard[] = [["text" => $note['title'], "callback_data" => "student_view_note_" . $note['id']]];
                }

                $inlineKeyboard[] = [["text" => "🔙 بازگشت", "callback_data" => "class_" . $classId]];

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $this->message['message_id'],
                    "text" => "📄 لیست جزوات این کلاس:",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        } elseif (strpos($callbackData, "student_view_note_") === 0) {
            $noteId = str_replace("student_view_note_", "", $callbackData);
            $note = $this->db->getClassNoteById($noteId);

            if ($note) {
                $uploadId = $note['upload_id'];
                $notes = $this->db->getClassNotesByUploadId($uploadId);

                foreach ($notes as $relatedNote) {
                    if ($relatedNote['file_type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id" => $this->chatId,
                            "photo" => $relatedNote['file_id'],
                            "caption" => "📄 " . $relatedNote['title']
                        ]);
                    } elseif ($relatedNote['file_type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id" => $this->chatId,
                            "document" => $relatedNote['file_id'],
                            "caption" => "📄 " . $relatedNote['title']
                        ]);
                    }
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "🔙 برای بازگشت به لیست جزوات کلیک کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "🔙 بازگشت", "callback_data" => "student_class_notes"]]
                        ]
                    ])
                ]);
            } else {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ جزوه یافت نشد.",
                ]);
            }
            return;
        } elseif ($callbackData === "my_assignments") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);

            if (!$classId) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ کلاس انتخاب‌شده نامعتبر است. لطفاً دوباره تلاش کنید."
                ]);
                return;
            }

            $assignments = $this->db->getAssignmentsByStudent($classId, $this->chatId);

            if (empty($assignments)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📁 شما هنوز تمرینی برای این کلاس ارسال نکرده‌اید."
                ]);
            } else {
                require_once 'jdf.php';

                foreach ($assignments as $assignment) {
                    $caption = $assignment['caption'] ?? 'بدون توضیحات';
                    $submittedAt = jdate('Y/m/d H:i', strtotime($assignment['submitted_at']));

                    if ($assignment['file_type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id" => $this->chatId,
                            "photo" => $assignment['file_id'],
                            "caption" => "📅 تاریخ ارسال: $submittedAt\n✏️ توضیحات: $caption",
                        ]);
                    } elseif ($assignment['file_type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id" => $this->chatId,
                            "document" => $assignment['file_id'],
                            "caption" => "📅 تاریخ ارسال: $submittedAt\n✏️ توضیحات: $caption",
                        ]);
                    }
                }
            }
            return;
        } elseif ($callbackData === "class_notes") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $notes = $this->db->getClassNotes($classId);

            if (empty($notes)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📄 هنوز جزوه‌ای برای این کلاس ثبت نشده است."
                ]);
            } else {
                foreach ($notes as $note) {
                    $this->sendRequest("sendMessage", [
                        "chat_id" => $this->chatId,
                        "text" => "📄 " . $note['title'] . "\n\n" . $note['description'],
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "📥 دانلود", "url" => $note['file_url']]]
                            ]
                        ])
                    ]);
                }
            }
            return;
        } elseif ($callbackData === "class_exam_date") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $examDate = $this->db->getClassExamDate($classId);

            if ($examDate) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📅 تاریخ امتحان میان‌ترم:\n" . $examDate
                ]);
            } else {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📅 هنوز تاریخی برای امتحان میان‌ترم ثبت نشده است."
                ]);
            }
            return;
        } elseif ($callbackData === "submit_assignment") {
            $this->fileHandler->saveState($this->chatId, "awaiting_assignment_upload");

            $assignmentId = uniqid("assign_", true);
            $this->fileHandler->saveAssignmentId($this->chatId, $assignmentId);

            $response=$this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "📤 لطفاً فایل تمرین خود را ارسال کنید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "پایان ارسال تمرین‌ها", "callback_data" => "finish_assignment_upload"]]
                    ]
                ])
            ]);
             if (isset($response['result']['message_id'])) {
                        $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
             }
            return;
        } elseif ($callbackData === "finish_assignment_upload") {
            $messageIds = $this->fileHandler->getMessageIds($this->chatId);
            if (!empty($messageIds)) {
                foreach ($messageIds as $messageId) {
                    $this->sendRequest("deleteMessage", [
                        "chat_id" => $this->chatId,
                        "message_id" => $messageId
                    ]);
                }
                $this->fileHandler->clearMessageIds($this->chatId);
            }

            $assignmentId = $this->fileHandler->getAssignmentId($this->chatId);
            $classId = $this->fileHandler->getSelectedClass($this->chatId);

            if (!$assignmentId || !$classId) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ خطایی در ثبت تمرین رخ داده است. لطفاً دوباره تلاش کنید."
                ]);
                return;
            }

            $assignments = $this->db->getAssignmentsByAssignmentId($assignmentId);
            $studentInfo = $this->db->getStudentInfo($this->chatId);
            $classInfo = $this->db->getClassInfo($classId);

            if (empty($assignments)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ تمرینی برای ارسال یافت نشد."
                ]);
            } else {
                $adminChatId = '235303991';

                require_once 'jdf.php';

                foreach ($assignments as $assignment) {
                    $caption = $assignment['caption'] ?? 'بدون توضیحات';
                    $submittedAt = jdate('Y/m/d H:i', strtotime($assignment['submitted_at']));
                    $studentName = $studentInfo['student_name'] ?? 'نامشخص';
                    $className = $classInfo['name'] ?? 'نامشخص';

                    $messageCaption = "👤 دانشجو: $studentName\n" .
                        "📚 کلاس: $className\n" .
                        "📅 تاریخ ارسال: $submittedAt\n" .
                        "✏️ توضیحات: $caption";

                    $inlineKeyboard = [
                        [
                            ["text" => "✅ تایید", "callback_data" => "approve_assignment_" . $assignment['id']],
                            ["text" => "⚠️ مشکوک به تقلب", "callback_data" => "suspect_assignment_" . $assignment['id']],
                            ["text" => "❌ تقلب", "callback_data" => "reject_assignment_" . $assignment['id']]
                        ]
                    ];

                    if ($assignment['file_type'] === 'photo') {
                        $this->sendRequest("sendPhoto", [
                            "chat_id" => $adminChatId,
                            "photo" => $assignment['file_id'],
                            "caption" => $messageCaption,
                            "parse_mode" => "HTML",
                            "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                        ]);
                    } elseif ($assignment['file_type'] === 'document') {
                        $this->sendRequest("sendDocument", [
                            "chat_id" => $adminChatId,
                            "document" => $assignment['file_id'],
                            "caption" => $messageCaption,
                            "parse_mode" => "HTML",
                            "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                        ]);
                    }
                }
            }


            $this->fileHandler->clearAssignmentId($this->chatId);
            $this->fileHandler->saveState($this->chatId, null);

            $response = $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🎉 ارسال ‌ها پایان یافت. تمرین‌های شما برای بررسی به استاد ارسال شدند."
            ]);
            sleep(5);

            $this->showMainMenu($this->db->isAdmin($chatId), $response['result']['message_id']);
            return;
        } elseif ($callbackData === "finish_note_upload") {
            $this->fileHandler->clearUploadId($this->chatId);
            $messageIds = $this->fileHandler->getMessageIds($this->chatId);
            if (!empty($messageIds)) {
                foreach ($messageIds as $messageId) {
                    $this->sendRequest("deleteMessage", [
                        "chat_id" => $this->chatId,
                        "message_id" => $messageId
                    ]);
                }
                $this->fileHandler->clearMessageIds($this->chatId);
            }

            $this->fileHandler->saveState($this->chatId, null);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🎉 آپلود جزوه‌ها پایان یافت. جزوه‌های شما با موفقیت ثبت شدند.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "بازگشت به منوی اصلی", "callback_data" => "main_menu"]]
                    ]
                ])
            ]);
            return;
        } elseif (strpos($callbackData, "approve_assignment_") === 0) {
            $assignmentId = str_replace("approve_assignment_", "", $callbackData);

            $assignment = $this->db->getAssignmentDetails($assignmentId);
            $studentName = $assignment['student_name'] ?? 'نامشخص';

            $this->db->updateAssignmentStatus($assignmentId, 'approved');

            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "✅ تمرین $studentName تایید شد.",
                "reply_to_message_id" => $callbackQuery["message"]["message_id"]
            ]);

            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "🔙 به منوی مدیریت تمرین‌ها بازگردید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت", "callback_data" => "manage_class_"]]
                    ]
                ])
            ]);

            return;
        } elseif (strpos($callbackData, "suspect_assignment_") === 0) {
            $assignmentId = str_replace("suspect_assignment_", "", $callbackData);

            $assignment = $this->db->getAssignmentDetails($assignmentId);
            $studentName = $assignment['student_name'] ?? 'نامشخص';

            $this->db->updateAssignmentStatus($assignmentId, 'suspect');

            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "⚠️ تمرین $studentName مشکوک به تقلب ثبت شد.",
                "reply_to_message_id" => $callbackQuery["message"]["message_id"]
            ]);


            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "🔙 به منوی مدیریت تمرین‌ها بازگردید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت", "callback_data" => "manage_class_"]]
                    ]
                ])
            ]);

            return;
        } elseif (strpos($callbackData, "reject_assignment_") === 0) {
            $assignmentId = str_replace("reject_assignment_", "", $callbackData);

            $assignment = $this->db->getAssignmentDetails($assignmentId);
            $studentName = $assignment['student_name'] ?? 'نامشخص';

            $this->db->updateAssignmentStatus($assignmentId, 'rejected');

            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "❌ تمرین $studentName تقلب ثبت شد.",
                "reply_to_message_id" => $callbackQuery["message"]["message_id"]
            ]);

            $this->sendRequest("sendMessage", [
                "chat_id" => $callbackQuery["message"]["chat"]["id"],
                "text" => "🔙 به منوی مدیریت تمرین‌ها بازگردید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت", "callback_data" => "manage_class_"]]
                    ]
                ])
            ]);

            return;
        } elseif ($callbackData === "go_to_my_classes") {
            $classes = $this->db->getStudentClasses($this->chatId);

            if (empty($classes)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ شما هنوز در هیچ کلاسی ثبت‌نام نکرده‌اید."
                ]);
            } else {
                $inlineKeyboard = [];
                foreach ($classes as $class) {
                    $inlineKeyboard[] = [
                        ["text" => $class['name'], "callback_data" => "class_" . $class['id']]
                    ];
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "📚 کلاس‌های شما:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => $inlineKeyboard
                    ])
                ]);
            }
            return;
        } elseif ($callbackData === "create_class") {
            $this->fileHandler->saveState($chatId, "creating_class");
            $this->fileHandler->saveMessageId($chatId, $messageId);
            $this->sendRequest("editMessageText", [
                "chat_id" => $chatId,
                "message_id" => $messageId,
                "text" => "لطفاً نام کلاس را وارد کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
        } elseif ($callbackData === "cancel_action") {
            $this->fileHandler->saveState($chatId, null);
            $this->showMainMenu($this->db->isAdmin($chatId), $messageId);
            return;
        } else {
            $this->sendRequest("answerCallbackQuery", [
                "callback_query_id" => $callbackQueryId,
                "text" => "عملیات نامعتبر است.",
                "show_alert" => true,
            ]);
        }
    }


    public function handleRequest()
    {


        $this->db->saveUser($this->message["from"]);
        $state = $this->fileHandler->getState($this->chatId);
        $mediaGroupId = $this->fileHandler->getMediaGroupId($this->chatId);
        $sectionType = $this->fileHandler->getSectionType($this->chatId) ?? 'academy';
        $isAdmin = $this->db->isAdmin($this->chatId);

        if (strpos($this->text, "/start ") === 0) {
            $token = substr($this->text, 7);

            $classData = $this->db->getClassByToken($token);

            if ($classData) {
                $classId = $classData['id'];

                if ($this->db->isStudentRegistered($classId, $this->chatId)) {
                    $this->sendRequest("sendMessage", [
                        "chat_id" => $this->chatId,
                        "text" => "✅ شما قبلاً در این کلاس ثبت‌نام کرده‌اید!",
                    ]);
                    return;
                }

                $this->fileHandler->saveState($this->chatId, "awaiting_class_password");
                $this->fileHandler->saveClassToken($this->chatId, $token);

                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "🔒 لطفاً رمز کلاس را وارد کنید:",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [$this->cancelButton()]
                        ]
                    ])
                ]);

                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->saveMessageId($this->chatId, $response['result']['message_id']);
                }
            } else {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ کلاس یافت نشد.",
                ]);
            }
            return;
        }


        if ($state && strpos($state, "sending_announcement_") === 0) {
            $classId = str_replace("sending_announcement_", "", $state);
            $message = $this->text;

            if (empty(trim($message))) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ پیام نمی‌تواند خالی باشد. لطفاً دوباره تلاش کنید.",
                ]);
                return;
            }

            $students = $this->db->getStudentsByClassId($classId);

            if (empty($students)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ هیچ دانشجویی در این کلاس ثبت نشده است.",
                ]);
            } else {
                foreach ($students as $student) {
                    $this->sendRequest("sendMessage", [
                        "chat_id" => $student['chat_id'],
                        "text" => "📢 *اطلاع‌رسانی جدید از کلاس*\n\n" . $message,
                        "parse_mode" => "Markdown"
                    ]);
                }

                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "✅ پیام شما با موفقیت به تمام دانشجویان کلاس ارسال شد.",
                ]);
            }

            $this->fileHandler->saveState($this->chatId, null);

            return;
        }
        if ($state === "sending_global_announcement") {
            $message = $this->text;
            $this->deleteMessageWithDelay();
            $MessageId = $this->fileHandler->getMessageId($this->chatId);

            if (empty(trim($message))) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ پیام نمی‌تواند خالی باشد. لطفاً دوباره تلاش کنید.",
                ]);
                return;
            }

            $users = $this->db->getAllUsers();

            if (empty($users)) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ هیچ کاربری ثبت نشده است.",
                ]);
            } else {
                foreach ($users as $user) {
                    if ($user['chat_id'] == $this->chatId) {
                        continue;
                    }

                    $this->sendRequest("sendMessage", [
                        "chat_id" => $user['chat_id'],
                        "text" => "📢 <b>اطلاع‌رسانی جدید</b>\n\n<blockquote>" . htmlspecialchars($message) . "</blockquote>",
                        "parse_mode" => "HTML"
                    ]);
                }

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $MessageId,
                    "text" => "✅ پیام شما با موفقیت ارسال شد.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "🏠 برگشت به منو اصلی", "callback_data" => "go_to_main_menu"]
                            ]
                        ]
                    ])
                ]);
            }

            $this->fileHandler->saveState($this->chatId, null);

            return;
        }
        if (strpos($state, "editing_exam_title_") === 0) {
            $examId = str_replace("editing_exam_title_", "", $state);

            if (empty(trim($this->text))) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ *عنوان امتحان نمی‌تواند خالی باشد.*",
                    "parse_mode" => "Markdown"
                ]);
                return;
            }

            $newTitle = $this->text;
            $this->db->updateExamTitle($examId, $newTitle);

            $this->fileHandler->saveState($this->chatId, null);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ *عنوان امتحان با موفقیت به‌روزرسانی شد.*\n\n" .
                    "📘 *عنوان جدید:* " . $newTitle,
                "parse_mode" => "Markdown"
            ]);
            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🔙 به منوی مدیریت کلاس بازگشتید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت به مدیریت کلاس", "callback_data" => "manage_class_" . $classId]]
                    ]
                ])
            ]);
            return;
        }
        if ($state === "awaiting_class_password") {
            $token = $this->fileHandler->getClassToken($this->chatId);
            $classData = $this->db->getClassByToken($token);
            $this->deleteMessageWithDelay();
            $MessageId = $this->fileHandler->getMessageId($this->chatId);
            if ($classData) {
                if (trim($this->text) === $classData['password']) {
                    $this->fileHandler->saveState($this->chatId, "awaiting_student_info");

                    $this->sendRequest("editMessageText", [
                        "chat_id" => $this->chatId,
                        "message_id" => $MessageId,
                        "text" => "✅ رمز صحیح است.\nلطفاً نام و نام خانوادگی خود را وارد کنید:",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [$this->cancelButton()]
                            ]
                        ])
                    ]);
                } else {
                    $this->sendRequest("editMessageText", [
                        "chat_id" => $this->chatId,
                        "message_id" => $MessageId,
                        "text" => "❌ رمز اشتباه است. لطفاً دوباره تلاش کنید:",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [$this->cancelButton()]
                            ]
                        ])
                    ]);
                }
            } else {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $MessageId,
                    "text" => "⚠️ کلاس یافت نشد.",
                ]);
            }
            return;
        }


        if ($state && strpos($state, "editing_class_") === 0) {
            $parts = explode("_", $state);
            $field = $parts[2];
            $classId = $parts[3];
            $this->deleteMessageWithDelay();
            $messageId = $this->fileHandler->getMessageId($this->chatId);

            $inlineKeyboard = [
                [["text" => "🔙 بازگشت", "callback_data" => "edit_class_" . $classId]]
            ];

            if ($field === "name") {
                $this->db->updateClass($classId, ["name" => $this->text]);
                $this->fileHandler->saveState($this->chatId, null);
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "✅ نام کلاس با موفقیت به‌روزرسانی شد.",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            } elseif ($field === "time") {
                $this->db->updateClass($classId, ["time" => $this->text]);
                $this->fileHandler->saveState($this->chatId, null);
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "✅ زمان کلاس با موفقیت به‌روزرسانی شد.",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            } elseif ($field === "password") {
                $this->db->updateClass($classId, ["password" => $this->text]);
                $this->fileHandler->saveState($this->chatId, null);
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "✅ رمز کلاس با موفقیت به‌روزرسانی شد.",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            } elseif ($field === "link") {
                $this->db->updateClass($classId, ["token" => $this->text]);
                $botUsername = "coursesdelavarian_bot";
                $classLink = "https://t.me/$botUsername?start=" . htmlspecialchars($this->text);
                $this->fileHandler->saveState($this->chatId, null);
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "✅ لینک کلاس با موفقیت به‌روزرسانی شد:\n🌐 <code>$classLink</code>",
                    "parse_mode" => "HTML",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            } else {
                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $messageId,
                    "text" => "⚠️ عملیات نامعتبر است. لطفاً دوباره تلاش کنید.",
                    "reply_markup" => json_encode(["inline_keyboard" => $inlineKeyboard])
                ]);
            }
            return;
        }

        if ($state === "awaiting_assignment_upload") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $assignmentId = $this->fileHandler->getAssignmentId($this->chatId);

            if (!$classId || !$assignmentId) {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ کلاس یا تمرین انتخاب‌شده نامعتبر است. لطفاً دوباره تلاش کنید."
                ]);
                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                }
                return;
            }

            if (isset($this->message['media_group_id'])) {
                $mediaGroupId = $this->message['media_group_id'];

                if (isset($this->message['document']) || isset($this->message['photo'])) {
                    $fileType = isset($this->message['photo']) ? 'photo' : 'document';
                    $fileId = isset($this->message['photo']) ? end($this->message['photo'])['file_id'] : $this->message['document']['file_id'];
                    $caption = $this->message['caption'] ?? null;

                    $this->db->saveAssignmentFile($classId, $assignmentId, $this->chatId, $fileId, $fileType, $caption, $mediaGroupId);

                    $response = $this->sendRequest("sendMessage", [
                        "chat_id" => $this->chatId,
                        "text" => "✅ فایل گروهی شما ذخیره شد. فایل‌های دیگری ارسال کنید یا دکمه 'پایان ارسال تمرین‌ها' را کلیک کنید.",
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [["text" => "پایان ارسال تمرین‌ها", "callback_data" => "finish_assignment_upload"]]
                            ]
                        ])
                    ]);

                    if (isset($response['result']['message_id'])) {
                        $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                    }
                }
            } elseif (isset($this->message['document']) || isset($this->message['photo'])) {
                $fileType = isset($this->message['photo']) ? 'photo' : 'document';
                $fileId = isset($this->message['photo']) ? end($this->message['photo'])['file_id'] : $this->message['document']['file_id'];
                $caption = $this->message['caption'] ?? null;

                $this->db->saveAssignmentFile($classId, $assignmentId, $this->chatId, $fileId, $fileType, $caption);

                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "✅ فایل شما ذخیره شد. فایل دیگری ارسال کنید یا دکمه 'پایان ارسال تمرین‌ها' را کلیک کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "پایان ارسال تمرین‌ها", "callback_data" => "finish_assignment_upload"]]
                        ]
                    ])
                ]);

                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                }
            } else {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ لطفاً یک فایل معتبر ارسال کنید یا دکمه 'پایان ارسال تمرین‌ها' را کلیک کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "پایان ارسال تمرین‌ها", "callback_data" => "finish_assignment_upload"]]
                        ]
                    ])
                ]);

                if (isset($response['result']['message_id'])) {
                    $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
                }
            }
            return;
        }


        if ($state && strpos($state, "editing_exam_date_") === 0) {
            $examId = str_replace("editing_exam_date_", "", $state);

            $this->db->updateMidtermExamDate($examId, $this->text);

            $this->fileHandler->saveState($this->chatId, null);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ تاریخ امتحان میان‌ترم با موفقیت ویرایش شد.",
            ]);

            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🔙 به منوی مدیریت کلاس بازگشتید.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت به مدیریت کلاس", "callback_data" => "manage_class_" . $classId]]
                    ]
                ])
            ]);

            return;
        }

        if ($state && strpos($state, "adding_note_") === 0) {
            $classId = str_replace("adding_note_", "", $state);
            $this->fileHandler->saveNoteTitle($this->chatId, $this->text);
            $this->fileHandler->saveState($this->chatId, "awaiting_note_file_" . $classId);
            $this->deleteMessageWithDelay();
            $messageId = $this->fileHandler->getMessageId($this->chatId);
            $response = $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $messageId,
                "text" => "📁 لطفاً فایل یا عکس جزوه را ارسال کنید:",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);
            return;
        }

        if ($state === "adding_exam") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);

            if (empty(trim($this->text))) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ *لطفاً یک عنوان معتبر وارد کنید.*",
                    "parse_mode" => "Markdown",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                        ]
                    ])
                ]);
                return;
            }

            $this->fileHandler->saveExamTitle($this->chatId, $this->text);
            $this->fileHandler->saveState($this->chatId, "adding_date");

            $testState = $this->fileHandler->getState($this->chatId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "📅 *لطفاً تاریخ امتحان را وارد کنید* (می‌توانید _متن فارسی_ یا هر قالب دلخواه وارد کنید):",
                "parse_mode" => "Markdown",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ لغو عملیات", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);

            return;
        }

        if ($state === "adding_date") {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $examTitle = $this->fileHandler->getExamTitle($this->chatId);

            if (!$examTitle) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ *خطا:* عنوان امتحان پیدا نشد. لطفاً دوباره تلاش کنید.",
                    "parse_mode" => "Markdown"
                ]);

                $this->fileHandler->saveState($this->chatId, null);
                return;
            }

            $examDate = $this->text;

            $this->db->addMidtermExam($classId, $examTitle, $examDate);

            $this->fileHandler->saveState($this->chatId, null);
            $this->fileHandler->clearExamTitle($this->chatId);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ امتحان جدید با عنوان *" . $examTitle . "* و تاریخ *" . $examDate . "* با موفقیت ثبت شد.",
                "parse_mode" => "Markdown"
            ]);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "🔙 *به منوی مدیریت امتحانات بازگشتید.*",
                "parse_mode" => "Markdown",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت", "callback_data" => "class_exam_" . $classId]]
                    ]
                ])
            ]);

            return;
        }

        if ($state && strpos($state, "awaiting_note_file") === 0) {
            $classId = $this->fileHandler->getSelectedClass($this->chatId);
            $noteTitle = $this->fileHandler->getNoteTitle($this->chatId);

            if (!$classId || !$noteTitle) {
                $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "⚠️ کلاس یا عنوان جزوه انتخاب‌شده نامعتبر است. لطفاً دوباره تلاش کنید."
                ]);
                return;
            }

            $uploadId = $this->fileHandler->getUploadId($this->chatId);

            if (isset($this->message['document']) || isset($this->message['photo'])) {
                $fileType = isset($this->message['photo']) ? 'photo' : 'document';
                $fileId = isset($this->message['photo']) ? end($this->message['photo'])['file_id'] : $this->message['document']['file_id'];
                $caption = $this->message['caption'] ?? null;

                $this->db->saveClassNote($classId, $noteTitle, $fileId, $fileType, $caption, $uploadId);

                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "✅ فایل شما ذخیره شد. فایل دیگری ارسال کنید یا دکمه 'پایان ارسال جزوه‌ها' را کلیک کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "پایان ارسال جزوه‌ها", "callback_data" => "finish_note_upload"]]
                        ]
                    ])
                ]);
                $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);

            } else {
                $response = $this->sendRequest("sendMessage", [
                    "chat_id" => $this->chatId,
                    "text" => "❌ لطفاً یک فایل معتبر ارسال کنید یا دکمه 'پایان ارسال جزوه‌ها' را کلیک کنید.",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "پایان ارسال جزوه‌ها", "callback_data" => "finish_note_upload"]]
                        ]
                    ])
                ]);
                $this->fileHandler->addMessageId($this->chatId, $response['result']['message_id']);

            }
            return;
        }


        if ($state === "awaiting_student_info") {
            $this->fileHandler->saveStudentName($this->chatId, $this->text);
            $this->fileHandler->saveState($this->chatId, "awaiting_student_id");
            $this->deleteMessageWithDelay();

            $studentName = $this->fileHandler->getStudentName($this->chatId);
            $MessageId = $this->fileHandler->getMessageId($this->chatId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $MessageId,
                "parse_mode" => "Markdown",
                "text" => "✅ **$studentName عزیز، ثبت‌نام شما با موفقیت انجام شد**\n📌 لطفاً شماره دانشجویی خود را وارد کنید تا فرایند ثبت‌نام تکمیل شود.",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        }


        if ($state === "awaiting_student_id") {
            if (!ctype_digit($this->text)) {
                $MessageId = $this->fileHandler->getMessageId($this->chatId);
                $this->deleteMessageWithDelay();

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $MessageId,
                    "text" => "⚠️ شماره دانشجویی باید فقط شامل اعداد باشد.\nلطفاً شماره صحیح را وارد نمایید.",
                    "parse_mode" => "Markdown",
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                        ]
                    ])
                ]);
                return;
            }
            $this->deleteMessageWithDelay();
            $studentName = $this->fileHandler->getStudentName($this->chatId);
            $token = $this->fileHandler->getClassToken($this->chatId);
            $classData = $this->db->getClassByToken($token);

            if ($classData) {
                $this->db->registerStudentToClass($classData['id'], $this->chatId, $studentName, $this->text);

                $this->fileHandler->saveState($this->chatId, null);
                $this->fileHandler->clearClassToken($this->chatId);
                $this->fileHandler->clearStudentName($this->chatId);

                $MessageId = $this->fileHandler->getMessageId($this->chatId);

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $MessageId,
                    "text" => "🎉 * $studentName* \n" .
                        "✅ فرآیند ثبت‌نام شما با موفقیت تکمیل شد و به کلاس *«" . htmlspecialchars($classData['name']) . "»* افزوده شدید.\n\n" .
                        "📚 شما اکنون به محتوای آموزشی این کلاس دسترسی دارید.",
                    "parse_mode" => "Markdown"
                ]);

                $this->showMainMenu($this->db->isAdmin($this->chatId));
            } else {
                $MessageId = $this->fileHandler->getMessageId($this->chatId);

                $this->sendRequest("editMessageText", [
                    "chat_id" => $this->chatId,
                    "message_id" => $MessageId,
                    "text" => "⚠️ کلاس مورد نظر یافت نشد. لطفاً مجدداً تلاش نمایید.",
                    "parse_mode" => "Markdown"
                ]);
            }
            return;
        }


        if ($state && strpos($state, "editing_student_") === 0) {
            $parts = explode("_", $state);
            $studentChatId = $parts[1];
            $classId = $parts[2];

            $this->db->updateStudentName($studentChatId, $this->text);

            $this->fileHandler->saveState($this->chatId, null);

            $this->sendRequest("sendMessage", [
                "chat_id" => $this->chatId,
                "text" => "✅ نام دانشجو با موفقیت به‌روزرسانی شد."
            ]);

            return;
        }


        if ($state === "creating_class") {
            $className = $this->text;
            $this->fileHandler->saveClassName($this->chatId, $className);
            $this->deleteMessageWithDelay();
            $MessageId = $this->fileHandler->getMessageId($this->chatId);
            $this->fileHandler->saveState($this->chatId, "awaiting_class_time");

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $MessageId,
                "text" => "کلاس <b>«{$className}»</b> با موفقیت ایجاد شد. لطفاً زمان کلاس را وارد کنید (مثال: دوشنبه‌ها ساعت 10 تا 12):",
                "parse_mode" => "HTML",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        }


        if ($state === "awaiting_class_time") {
            $this->fileHandler->saveClassTime($this->chatId, $this->text);
            $this->fileHandler->saveState($this->chatId, "class_password");
            $this->deleteMessageWithDelay();
            $MessageId = $this->fileHandler->getMessageId($this->chatId);
            $className = $this->fileHandler->getClassName($this->chatId);
            $classTime = $this->fileHandler->getClassTime($this->chatId);

            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $MessageId,
                "text" => "کلاس <b>«{$className}»</b> در زمان <b>«{$classTime}»</b> ثبت شد.\nلطفاً رمز کلاس را وارد کنید:",
                "parse_mode" => "HTML",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "❌ انصراف", "callback_data" => "cancel_action"]]
                    ]
                ])
            ]);
            return;
        }

        if ($state === "class_password") {
            $this->fileHandler->saveClassPassword($this->chatId, $this->text);
            $MessageId = $this->fileHandler->getMessageId($this->chatId);
            $this->deleteMessageWithDelay();

            $classData = $this->fileHandler->finalizeClassData($this->chatId);

            $this->db->saveClass($this->chatId, $classData);

            $this->fileHandler->saveState($this->chatId, null);

            $token = $this->db->getLastInsertedClassToken($this->chatId);

            $botUsername = "coursesdelavarian_bot";
            $classLink = "https://t.me/$botUsername?start=" . htmlspecialchars($token);


            $this->sendRequest("editMessageText", [
                "chat_id" => $this->chatId,
                "message_id" => $MessageId,
                "text" => "✅ کلاس با موفقیت ایجاد شد:\n" .
                    "📚 نام: " . htmlspecialchars($classData['name']) . "\n" .
                    "⏰ زمان: " . htmlspecialchars($classData['time']) . "\n" .
                    "🔒 رمز: " . htmlspecialchars($classData['password']) . "\n" .
                    "🔗 لینک کلاس: <a href=\"$classLink\">کلیک کنید</a>",
                "parse_mode" => "HTML",
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "🔙 بازگشت به مدیریت", "callback_data" => "main_menu"]]
                    ]
                ])
            ]);
            return;
        }

        if ($this->text == "/start") {
            $this->fileHandler->saveState($this->chatId, null);
            $isAdmin = $this->db->isAdmin($this->chatId);;


            $this->db->saveUser($this->message["from"], $isAdmin);

            $this->showMainMenu($isAdmin);

            return;
        }


    }

}
