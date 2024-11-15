<?php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null; // Hole die ID, falls vorhanden

// Verzeichnis, in dem die To-Do-Dateien gespeichert werden
$directory = 'q';

// Funktion zum Dateipfad basierend auf der ID
function getFilePath($id) {
    global $directory;
    return "$directory/$id.json";
}

// Überprüfen, ob das Verzeichnis existiert, und erstellen, falls nicht
if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}

// Hilfsfunktion zum Antworten
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Funktion zur Generierung einer eindeutigen ID
function getUniqueQuestId($id) {
    $file = getFilePath($id);
    while (file_exists($file)) {
        $id .= chr(rand(97, 122)); // Zufälliger Buchstabe (a-z)
        $file = getFilePath($id);
    }
    return $id; // Gib die eindeutige ID zurück
}

// Hilfsfunktion zur Neuindizierung der To-Do-Nummern
function reindexTodos($todos) {
    return array_map(function ($todo, $index) {
        $todo['number'] = $index + 1; // Setze die Nummer basierend auf der Position im Array
        return $todo;
    }, $todos, array_keys($todos));
}

// Funktion zum Laden der To-Do-Liste
function loadTodoList($file, $id) {
    $todoList = json_decode(file_get_contents($file), true);
    
    // Fallback, falls die Datei im alten Format ist
    if (!isset($todoList['id'], $todoList['name'], $todoList['created_at'])) {
        $todoList = [
            'id' => $id,
            'name' => 'Standard To-Do Liste',
            'created_at' => date('Y-m-d H:i:s'),
            'todos' => $todoList
        ];
    }

    return $todoList;
}

// GET-Anfrage: Lade die To-Do-Datei
if ($method === 'GET') {
    if ($id) {
        $file = getFilePath($id);
        
        // Überprüfe, ob die Anfrage nur zur ID-Überprüfung dient
        if (isset($_GET['check'])) {
            jsonResponse(['isUnique' => !file_exists($file)]);
        }

        if (file_exists($file)) {
            $todoList = loadTodoList($file, $id);
            jsonResponse($todoList); // Gebe die gesamte Struktur zurück
        } else {
            // Rückgabe einer leeren Liste mit zusätzlichen Eigenschaften
            jsonResponse([
                'id' => $id,
                'name' => 'Leere To-Do Liste',
                'created_at' => date('Y-m-d H:i:s'),
                'todos' => []
            ]);
        }
    } else {
        jsonResponse(['message' => 'Keine ID übergeben.'], 400);
    }
}

// POST-Anfrage: Füge neuen Task hinzu und erstelle die Datei, falls noch keine existiert
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newTask = $data['task'] ?? null;
    $listName = $data['name'] ?? 'Standard Liste'; // Standardname, falls kein Name übergeben wurde

    if ($newTask) {
        $file = getFilePath($id ?? uniqid()); // Wenn keine ID übergeben wurde, erstelle eine neue ID
        $todoList = file_exists($file) ? json_decode(file_get_contents($file), true) : [
            'id' => $id,
            'name' => $listName,
            'created_at' => date('c'),
            'todos' => []
        ];

        // Füge den neuen Task hinzu
        $todoList['todos'][] = ['task' => $newTask, 'completed' => false];

        // Nummerierung der Todos aktualisieren
        $todoList['todos'] = reindexTodos($todoList['todos']);

        // Speichere die aktualisierte Liste in der Datei
        file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT));
        jsonResponse(['message' => 'Task hinzugefügt', 'id' => $todoList['id'], 'todos' => $todoList['todos']]);
    } else {
        jsonResponse(['message' => 'Kein Task übermittelt'], 400);
    }
}

// PUT-Anfrage: Aktualisiere den Status eines Tasks oder nur den Titel
if ($method === 'PUT') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $taskNumber = $data['taskNumber'] ?? null; // Fortlaufende Nummer des Tasks (optional)
        $completed = $data['completed'] ?? null; // Neuer Status (optional)
        $newListName = $data['name'] ?? null; // Neuer Listenname

        $file = getFilePath($id);

        if (file_exists($file)) {
            $todoList = json_decode(file_get_contents($file), true);
            
            // Aktualisiere den Listennamen, wenn angegeben
            if ($newListName) {
                $todoList['name'] = $newListName;
            }
            
            // Suche nach dem Task, wenn die Tasknummer angegeben ist
            if ($taskNumber) {
                foreach ($todoList['todos'] as &$todo) {
                    if ($todo['number'] === $taskNumber) {
                        if ($completed !== null) {
                            $todo['completed'] = $completed; // Aktualisiere den Status, wenn angegeben
                        }
                        file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT));
                        jsonResponse(['message' => 'Task aktualisiert', 'todos' => $todoList['todos'], 'listName' => $todoList['name']]);
                    }
                }
                jsonResponse(['message' => 'Task nicht gefunden'], 404);
            } else {
                // Wenn keine Tasknummer angegeben ist, aber der Titel aktualisiert wurde
                file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT));
                jsonResponse(['message' => 'Titel aktualisiert', 'listName' => $todoList['name']]);
            }
        } else {
            // Wenn die Datei nicht existiert, erstelle sie mit der richtigen Struktur
            if ($newListName) {
                $todoList = [
                    'id' => $id,
                    'name' => $newListName,
                    'created_at' => date(DATE_ISO8601), // Aktuelles Datum im ISO 8601 Format
                    'todos' => [] // Leere Todo-Liste
                ];
                file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT));
                jsonResponse(['message' => 'Neue Liste erstellt und Titel gesetzt', 'listName' => $newListName, 'todos' => $todoList['todos']]);
            } else {
                jsonResponse(['message' => 'Ungültige Daten übermittelt'], 400);
            }
        }
    } else {
        jsonResponse(['message' => 'Keine ID übermittelt'], 400);
    }
}

// DELETE-Anfrage: Lösche einen Task anhand der Nummer
if ($method === 'DELETE') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $taskNumber = $data['taskNumber'] ?? null; // Fortlaufende Nummer des Tasks

        if (!$taskNumber) {
            jsonResponse(['message' => 'Ungültige Daten übermittelt'], 400);
        }

        $file = getFilePath($id);

        if (file_exists($file)) {
            $todoList = json_decode(file_get_contents($file), true);

            // Filtere die Tasks anhand der "number"-Eigenschaft
            $todoList['todos'] = array_filter($todoList['todos'], function ($todo) use ($taskNumber) {
                return $todo['number'] !== $taskNumber; // Behalte alle Todos, die nicht gelöscht werden
            });

            // Neuindizieren der verbleibenden Tasks
            $todoList['todos'] = reindexTodos(array_values($todoList['todos']));

            file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT)); // Speichere die aktualisierte Liste
            jsonResponse(['message' => 'Task gelöscht', 'todos' => $todoList['todos']]);
        } else {
            jsonResponse(['message' => 'Datei nicht gefunden'], 404);
        }
    } else {
        jsonResponse(['message' => 'Keine ID übermittelt'], 400);
    }
}


// Haupt-Download-Logik
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    $id = $_GET['id'] ?? null; // Die Quest-ID
    $format = $_GET['format'] ?? 'json'; // Das gewünschte Format (standardmäßig JSON)

    if ($id) {
        // Datei einlesen
        $filePath = getFilePath($id); // Funktion, die den Dateipfad für die gegebene ID ermittelt

        if (file_exists($filePath)) {
            $todos = file_get_contents($filePath);
            $todosArray = json_decode($todos, true);

            switch ($format) {
                case 'pdf':
                    downloadAsPDF($todosArray, $id);
                    break;
                case 'markdown':
                    downloadAsMarkdown($todosArray, $id);
                    break;
                case 'excel':
                    downloadAsExcel($todosArray, $id); // Download als CSV
                    break;
                default:
                    downloadAsJSON($todosArray, $id); // Standardmäßig JSON
            }
        } else {
            echo json_encode(['error' => 'To-Do-Liste nicht gefunden.']);
        }
    } else {
        echo json_encode(['error' => 'Keine ID angegeben.']);
    }
    exit();
}

// Funktion zum Download als PDF
function downloadAsPDF($todosArray, $id) {
    // Beispiel-Inhalt für die PDF-Datei
    $htmlContent = '<h1>To-Do Liste</h1><table border="1" style="width:100%; border-collapse: collapse;">';
    $htmlContent .= '<tr><th>Aufgabe</th><th>Status</th></tr>';

    foreach ($todosArray as $todo) {
        $htmlContent .= '<tr><td>' . htmlspecialchars($todo['task']) . '</td><td>' . htmlspecialchars($todo['status']) . '</td></tr>';
    }
    $htmlContent .= '</table>';

    // Temporäre HTML-Datei erstellen
    $tempHtmlFile = tempnam(sys_get_temp_dir(), 'todos') . '.html';
    file_put_contents($tempHtmlFile, $htmlContent);

    // Header für den PDF-Download setzen
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="todos_' . $id . '.pdf"');

    // HTML-Inhalt an den Browser senden und als PDF ausgeben
    readfile($tempHtmlFile);
    unlink($tempHtmlFile); // Temporäre Datei löschen
    exit();
}

// Funktion zum Download als Markdown
function downloadAsMarkdown($todosArray, $id) {
    $markdownContent = "# To-Do Liste\n\n| Aufgabe | Status |\n| ------- | ------ |\n";

    foreach ($todosArray as $todo) {
        $markdownContent .= "| " . htmlspecialchars($todo['task']) . " | " . htmlspecialchars($todo['status']) . " |\n";
    }

    header('Content-Type: text/markdown');
    header('Content-Disposition: attachment; filename="todos_' . $id . '.md"');
    echo $markdownContent;
    exit();
}

// Funktion zum Download als Excel (CSV)
function downloadAsExcel($todosArray, $id) {
    $csvContent = "Aufgabe,Status\n"; // CSV-Header

    foreach ($todosArray as $todo) {
        // Aufgaben und Status in CSV-Format hinzufügen
        $csvContent .= '"' . htmlspecialchars($todo['task']) . '","' . htmlspecialchars($todo['status']) . "\"\n";
    }

    // Header für den CSV-Download setzen
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="todos_' . $id . '.csv"'); // Korrekte Dateiendung
    header('Content-Length: ' . strlen($csvContent));
    echo $csvContent;
    exit();
}

// Funktion zum Download als JSON
function downloadAsJSON($todosArray, $id) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="todos_' . $id . '.json"');
    echo json_encode($todosArray);
    exit();
}