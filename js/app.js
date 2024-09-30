document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const taskInput = document.getElementById('todo-input');
    const titleElement = document.querySelector('h1[contenteditable="true"]');

    let questId = urlParams.get('id') || redirectToNewQuestId();

    if (window.location.hash === "#overlay") {
        document.body.classList.add("overlay");
        document.querySelector('header').classList.add('hide');
        //document.querySelector('main.todo-container h1').classList.add('show');
        document.querySelector('footer').classList.add('glass');
    }

    // Funktion, um den Titel beim Laden der Seite zu setzen
    function initializeTitle() {
        const storedTitle = localStorage.getItem(`questTitle_${questId}`);
        if (storedTitle) {
            titleElement.textContent = storedTitle; // Setze den Titel in das Element
        } else {
            titleElement.textContent = 'TODO Liste'; // Setze einen Standardtitel, falls keiner vorhanden ist
        }
    }

    // Initialisiere den Titel beim Laden der Seite
    initializeTitle();

    // Zeige die aktuelle Quest-ID im HTML an
    displayQuestId(questId);

    // Load existing todos for the given questId
    loadTodos(questId);

    // Event-Listener für das Storage-Event hinzufügen
    window.addEventListener('storage', (event) => {
        if (event.key === `todos_${questId}`) { // Nur reagieren, wenn die To-Dos geändert wurden
            const updatedTodos = JSON.parse(event.newValue);
            displayTodos(updatedTodos); // Zeige die aktualisierten To-Dos an
            console.info('To-Dos synchronisiert aus localStorage:', updatedTodos);
        }
    });

    // Add event listener to the add button
    document.getElementById('add-btn').addEventListener('click', addTodo);

    async function redirectToNewQuestId() {

        const newQuestId = generateNewQuestId();
        
        // Überprüfen, ob die ID eindeutig ist
        const uniqueId = await checkQuestIdUniqueness(newQuestId);
    
        // Weiterleiten mit der eindeutigen ID
        window.location.href = `?id=${uniqueId}`;
    }
    
    
    function generateNewQuestId() {
        const randomWord = generateRandomWord(3); // Zufälliges Wort mit 3 Silben
        const randomNumber = Math.floor(Math.random() * 100).toString().padStart(2, '0'); // Zufallszahl zwischen 00 und 99
        
        return `${randomWord}${randomNumber}`; // Wort und Zahl kombinieren
    }

    async function checkQuestIdUniqueness(id) {
        const response = await fetch(`server.php?id=${id}&check`);
        const data = await response.json();
    
        // Wenn die ID bereits existiert, hänge einen Buchstaben dran
        if (!data.isUnique) {
            const randomLetter = String.fromCharCode(97 + Math.floor(Math.random() * 26)); // Zufälliger Buchstabe
            return `${id}${randomLetter}`; // Hänge den Buchstaben an
        }
    
        return id; // ID ist eindeutig
    }

    // Event-Listener für die Eingabetaste im Textfeld
    taskInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault(); // Verhindere den Zeilenumbruch
            addTodo(); // Task hinzufügen
        }
    });

    document.getElementById('generate-new-id').addEventListener('click', function() {
        const newQuestId = generateNewQuestId(); // Funktion, um eine neue Quest-ID zu generieren
        window.location.href = `?id=${newQuestId}`; // Weiterleitung zur neuen URL
    });

    function generateRandomWord(length) {
        const syllables = ['ba', 'ka', 'la', 'mi', 'no', 'ra', 'sa', 'ta', 'zu', 'li', 'te', 'zi', 'lu', 'jo', 'fu', 're', 'ne', 'pe', 'vo', 'me', 'de', 'qi', 'po', 'to', 'ru', 'se', 'wu'];
        let word = '';
    
        for (let i = 0; i < length; i++) {
            const randomSyllable = syllables[Math.floor(Math.random() * syllables.length)]; // Zufällige Silbe auswählen
            word += randomSyllable; // Silbe zum Wort hinzufügen
        }
    
        return word; // Wort in Kleinbuchstaben zurückgeben
    }

    function displayQuestId(id) {
        const questIdElement = document.getElementById('quest-id');
        questIdElement.textContent = id; // Setze den Textinhalt auf die Quest-ID
    }

    // Event-Listener für den Kopier-Link
    document.getElementById('copy-quest-id').addEventListener('click', (e) => {
        e.preventDefault(); // Verhindert das Standardverhalten des Links
        copyToClipboard(questId); // Kopiere die Quest-ID in die Zwischenablage
    });

    async function copyToClipboard(id) {
        const currentUrl = window.location.origin; // Basis-URL der aktuellen Seite
        const textToCopy = `${currentUrl}/questboard/?id=${id}`; // Vollständige URL erstellen
        try {
            await navigator.clipboard.writeText(textToCopy); // Kopiere den Text
        } catch (err) {
            console.error('Fehler beim Kopieren: ', err);
            alert('Fehler beim Kopieren der Quest-ID.');
        }
    }
    
    // Event-Listener für Änderungen am Titel
    titleElement.addEventListener('input', async () => {
        const newTitle = titleElement.textContent.trim();
        await updateTitle(newTitle);
    });

    async function updateTitle(newTitle) {
        try {
            const response = await fetch(`server.php?id=${questId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ name: newTitle }), // Sende nur den neuen Titel
            });
    
            const result = await response.json();
            if (response.ok) {
                titleElement.textContent = newTitle; // Setze den neuen Titel im UI
                localStorage.setItem(`questTitle_${questId}`, newTitle); // Speichere im Local Storage
                console.info('Titel aktualisiert und gespeichert:', newTitle);
            } else {
                alert(result.message || 'Fehler beim Aktualisieren des Titels');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
        }
    }

    async function addTodo() {
        const taskInput = document.getElementById('todo-input');
        const taskText = taskInput.value.trim();

        if (!taskText) {
            return alert('Bitte gib eine Aufgabe ein!'); // Alert if the task text is empty
        }

        try {
            const response = await fetch(`server.php?id=${questId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ task: taskText }),
            });

            const result = await response.json();

            if (response.ok) {
                taskInput.value = ''; // Clear the input field
                displayTodos(result.todos); // Update the displayed todos
                saveTodosToLocalStorage(result.todos);
                console.info('Todos gespeichert');
            } else {
                alert(result.message || 'Fehler beim Hinzufügen des Tasks');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
        }
    }

    // Funktion zum Speichern der To-Dos und der Metadaten im localStorage
    function saveTodosToLocalStorage(questData) {
        // Speichere die gesamte Quest-Datenstruktur im localStorage
        localStorage.setItem(`quest_${questData.id}`, JSON.stringify(questData));
    }

    async function loadTodos(questId) {
        const localQuestData = loadTodosFromLocalStorage(questId);
        const localTodos = localQuestData.todos;
    
        try {
            const response = await fetch(`server.php?id=${questId}`);
            const serverData = await response.json(); // Lade die gesamte Datenstruktur (nicht nur die To-Dos)
    
            const { todos: serverTodos, name, created_at } = serverData;
    
            // Überprüfen, ob die To-Do-Daten aus dem localStorage gleich den Server-Daten sind
            if (JSON.stringify(localTodos) !== JSON.stringify(serverTodos)) {
                // Die Server-Daten sind aktuell und wir speichern sie im localStorage
                if (serverTodos.length > 0) {
                    saveTodosToLocalStorage(serverData); // Speichere die gesamte Datenstruktur
                }
                displayTodos(serverTodos);
                displayMetaInfo(name, created_at);
                console.info('Todos vom Server geladen:', serverTodos);
            } else {
                // Daten sind gleich, verwende die Daten aus localStorage
                displayTodos(localTodos);
                displayMetaInfo(name, created_at);
                console.info('Todos Lokal geladen:', localTodos);
            }
        } catch (error) {
            console.error('Error:', error);
            // Bei Fehler, verwende lokale To-Dos, wenn vorhanden
            if (localTodos.length > 0) {
                displayTodos(localTodos);
                displayMetaInfo(localQuestData.name, localQuestData.created_at); // Anzeige auch bei lokalen Daten
            }
        }
    }

    // Funktion zum Anzeigen der Metadaten im UI
    function displayMetaInfo(name, createdAt) {
        const nameElement = document.getElementById('quest-name'); // Beispiel-Element für den Namen
        const dateElement = document.getElementById('created-at'); // Beispiel-Element für das Erstellungsdatum

        if (nameElement) {
            nameElement.textContent = name;
        }

        if (dateElement) {
            dateElement.textContent = `Erstellt am: ${new Date(createdAt).toLocaleString()}`;
        }
    }
    
    // Funktion zum Laden der gesamten Quest-Datenstruktur aus localStorage
    function loadTodosFromLocalStorage(questId) {
        const questData = localStorage.getItem(`quest_${questId}`);
        return questData ? JSON.parse(questData) : { todos: [], name: '', created_at: '' }; // Standardwerte setzen
    }

    function displayTodos(todos) {
        const todoList = document.getElementById('todo-list');
        todoList.innerHTML = ''; // Clear the list

        todos.forEach(todo => {
            const todoItem = document.createElement('li');
            todoItem.setAttribute('data-number', todo.number); // Ändere das Attribut zu 'data-number'

            if (todo.completed) {
                todoItem.classList.add('completed');
            }

            // Checkbox erstellen
            const checkboxDiv = document.createElement('div');
            checkboxDiv.classList.add('checkbox');

            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.checked = todo.completed;

            // Checkbox dem Container hinzufügen
            checkboxDiv.appendChild(checkboxInput);

            // Füge das visuelle Checkbox-Element hinzu
            const checkboxSpan = document.createElement('span'); // Erstelle das Span für die Checkbox
            checkboxDiv.appendChild(checkboxSpan); // Füge das Span zur Checkbox hinzu

            // Event-Listener für Checkbox
            checkboxInput.addEventListener('change', async () => {
                todo.completed = checkboxInput.checked; // Update the completed status
                await toggleComplete(todo.number); // Pass die todo.number

                // Füge Klasse hinzu oder entferne sie je nach checked-Status
                todoItem.classList.toggle('completed', checkboxInput.checked);
            });

            // Event-Listener für das Span, um die Checkbox beim Klicken zu aktivieren
            checkboxSpan.addEventListener('click', () => {
                checkboxInput.checked = !checkboxInput.checked; // Toggle Checkbox
                checkboxInput.dispatchEvent(new Event('change')); // Trigger das change Event
            });

            // Text für den Task hinzufügen
            const taskText = document.createElement('span');
            taskText.classList.add('todo-text');
            taskText.textContent = todo.task;

            // Delete-Button erstellen
            const deleteBtn = document.createElement('div');
            deleteBtn.classList.add('delete-btn');
            deleteBtn.innerHTML = '<span class="material-symbols-outlined">backspace</span>';
            deleteBtn.style.visibility = 'hidden'; // Initially hide the delete button

            // Event-Listener für Delete-Button
            deleteBtn.addEventListener('click', async () => {
                await deleteTodo(todo.number); // Verwende hier die todo.number
            });

            // Mouse event to show the delete button
            todoItem.addEventListener('mouseenter', () => {
                deleteBtn.style.visibility= 'visible'; // Show delete button on hover
            });

            todoItem.addEventListener('mouseleave', () => {
                deleteBtn.style.visibility = 'hidden'; // Hide delete button when not hovering
            });

            // Füge alles zum todoItem hinzu
            todoItem.appendChild(checkboxDiv);
            todoItem.appendChild(taskText);
            todoItem.appendChild(deleteBtn);

            // Append das todoItem zur todoList
            todoList.appendChild(todoItem);
        });
    }

    async function toggleComplete(todoNumber) {
        try {
            // Aktuellen Status des Tasks abfragen (Checkbox ist checked oder nicht)
            const todoItem = document.querySelector(`[data-number="${todoNumber}"]`);
            const checkbox = todoItem.querySelector('input[type="checkbox"]');
            const completedStatus = checkbox.checked;
    
            // PUT-Request an das Backend mit dem aktuellen Status (completedStatus)
            const response = await fetch(`server.php?id=${questId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ taskNumber: todoNumber, completed: completedStatus }), // Hier wird completedStatus korrekt übermittelt
            });
    
            const result = await response.json();
            if (response.ok) {
                loadTodos(questId); // Lade die Todos erneut, um die Anzeige zu aktualisieren
            } else {
                alert(result.message || 'Fehler beim Aktualisieren des Tasks');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
        }
    }

    async function deleteTodo(todoNumber) {
        try {
            const response = await fetch(`server.php?id=${questId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ taskNumber: todoNumber }), // Sende die taskNumber
            });
    
            const result = await response.json();
            if (response.ok) {
                loadTodos(questId); // Lade die Todos erneut, um die Anzeige zu aktualisieren
            } else {
                alert(result.message || 'Fehler beim Löschen des Tasks');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
        }
    }
});
