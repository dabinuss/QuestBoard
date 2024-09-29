<?php
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null; // Hole die ID, falls vorhanden

// Verzeichnis, in dem die To-Do-Dateien gespeichert werden
$directory = 'q';

// Funktion zum Dateipfad basierend auf der ID
function getFilePath($id) {
    global $directory;
    return $directory . '/' . $id . '.json';
}

// Überprüfen, ob das Verzeichnis existiert, wenn nicht, dann erstellen
if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}

// Hilfsfunktion zum Antworten
function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function getUniqueQuestId($id) {
    // Überprüfe, ob die Datei existiert
    $file = getFilePath($id);
    
    // Falls die Datei existiert, generiere eine neue ID
    while (file_exists($file)) {
        $randomChar = chr(rand(97, 122)); // Zufälliger Buchstabe (a-z)
        $id .= $randomChar; // Hänge den Buchstaben an die ID an
        $file = getFilePath($id); // Aktualisiere den Dateipfad mit der neuen ID
    }

    return $id; // Gib die eindeutige ID zurück
}


// Hilfsfunktion zur Neuindizierung der To-Do-Nummern
function reindexTodos($todos) {
    foreach ($todos as $index => &$todo) {
        $todo['number'] = $index + 1; // Setze die Nummer basierend auf der Position im Array
    }
    return $todos;
}

// GET-Anfrage: Lade die To-Do-Datei
if ($method === 'GET') {
    if ($id) {
        $file = getFilePath($id);
        
        // Überprüfe, ob die Anfrage nur zur ID-Überprüfung dient
        if (isset($_GET['check'])) {
            // Wenn die Datei existiert, ist die ID nicht einzigartig
            if (file_exists($file)) {
                jsonResponse(['isUnique' => false]);
            } else {
                // Datei existiert nicht, ID ist einzigartig
                jsonResponse(['isUnique' => true]);
            }
        } else {
            // Wenn keine 'check'-Abfrage vorliegt, lade die To-Do-Liste
            if (file_exists($file)) {
                $todoList = json_decode(file_get_contents($file), true);

                // Überprüfen, ob die geladene Datei die neuen Eigenschaften enthält (id, name, created_at)
                if (!isset($todoList['id']) || !isset($todoList['name']) || !isset($todoList['created_at'])) {
                    // Fallback, falls die Datei in einem alten Format ist und diese Informationen nicht enthält
                    $todoList = [
                        'id' => $id,
                        'name' => 'Standard To-Do Liste',
                        'created_at' => date('Y-m-d H:i:s'),
                        'todos' => $todoList
                    ];
                }

                jsonResponse($todoList); // Gebe die gesamte Struktur zurück
            } else {
                // Datei existiert nicht, Rückgabe einer leeren Liste mit zusätzlichen Eigenschaften
                jsonResponse([
                    'id' => $id,
                    'name' => 'Leere To-Do Liste',
                    'created_at' => date('Y-m-d H:i:s'),
                    'todos' => []
                ]);
            }
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
        if (!$id) {
            // Wenn keine ID übergeben wurde, erstelle eine neue ID für die To-Do-Liste
            $id = uniqid();
        }

        $file = getFilePath($id);

        // Wenn die Datei existiert, lade die Liste mit den Metadaten und Aufgaben
        if (file_exists($file)) {
            $todoList = json_decode(file_get_contents($file), true);
        } else {
            // Initialisiere eine neue Liste mit Metadaten
            $todoList = [
                'id' => $id,
                'name' => $listName,
                'created_at' => date('c'), // ISO 8601 Format z.B. 2023-09-26T15:20:00+00:00
                'todos' => []
            ];
        }

        // Füge den neuen Task hinzu
        $todoList['todos'][] = ['task' => $newTask, 'completed' => false];

        // Nummerierung der Todos aktualisieren
        $todoList['todos'] = reindexTodos($todoList['todos']);

        // Speichere die aktualisierte Liste in der Datei
        file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT)); // Option für bessere Lesbarkeit
        jsonResponse(['message' => 'Task hinzugefügt', 'id' => $id, 'todos' => $todoList['todos']]);
    } else {
        jsonResponse(['message' => 'Kein Task übermittelt'], 400);
    }
}

// PUT-Anfrage: Aktualisiere den Status eines Tasks
if ($method === 'PUT') {
    if ($id) {
        $data = json_decode(file_get_contents('php://input'), true);

        $taskNumber = $data['taskNumber'] ?? null; // Fortlaufende Nummer des Tasks
        $completed = $data['completed'] ?? null; // Neuer Status
        $newListName = $data['name'] ?? null; // Neuer Listenname

        if ($taskNumber === null || $completed === null) {
            jsonResponse(['message' => 'Ungültige Daten übermittelt'], 400);
            exit;
        }

        $file = getFilePath($id);

        if (file_exists($file)) {
            $todoList = json_decode(file_get_contents($file), true);

            // Aktualisiere den Listennamen, wenn angegeben
            if ($newListName !== null) {
                $todoList['name'] = $newListName; // Aktualisiere den Namen
            }

            // Suche den Task anhand der Nummer in der "todos"-Liste und aktualisiere den Status
            foreach ($todoList['todos'] as &$todo) {
                if ($todo['number'] === $taskNumber) {
                    $todo['completed'] = $completed; // Aktualisiere den Status
                    file_put_contents($file, json_encode($todoList, JSON_PRETTY_PRINT));
                    jsonResponse(['message' => 'Task aktualisiert', 'todos' => $todoList['todos']]);
                    return; // Beende die Funktion nach erfolgreicher Aktualisierung
                }
            }

            jsonResponse(['message' => 'Task nicht gefunden'], 404);
        } else {
            jsonResponse(['message' => 'Datei nicht gefunden'], 404);
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

        if ($taskNumber === null) {
            jsonResponse(['message' => 'Ungültige Daten übermittelt'], 400);
            exit;
        }

        $file = getFilePath($id);

        if (file_exists($file)) {
            $todoList = json_decode(file_get_contents($file), true);

            // Filtere die Tasks anhand der "number"-Eigenschaft
            $todoList['todos'] = array_filter($todoList['todos'], function ($todo) use ($taskNumber) {
                return $todo['number'] !== $taskNumber; // Behalte alle Todos, die nicht gelöscht werden
            });

            // Neuindizieren der verbleibenden Tasks, um die Nummerierung fortlaufend zu halten
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