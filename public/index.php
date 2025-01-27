<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserId = $_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

$sessionId = session_id();

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"/>
    <title>Chat</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            width: 100%;
        }

        .chat-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
        }

        header {
            padding: 1rem;
            background: #f4f4f4;
            border-bottom: 1px solid #ccc;
        }

        .chat-layout {
            display: flex;
            height: calc(100vh - 60px); /* subtract header height if any */
        }

        .left-pane {
            width: 30%;
            border-right: 1px solid #ccc;
            padding: 1rem;
        }

        .search-bar {
            display: flex;
            margin-bottom: 1rem;
        }

        #user-search {
            flex: 1;
            padding: 0.5rem;
        }

        #search-button {
            padding: 0.5rem;
            margin-left: 0.5rem;
        }

        .chat-list-item {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .chat-list-item:hover {
            background: #eee;
        }

        .chat-list-item .chat-username {
            font-weight: bold;
        }

        .chat-list-item .chat-last-date {
            font-size: 0.8em;
            color: #666;
        }

        /* Right pane: the active conversation */
        .right-pane {
            width: 70%;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        .chat-header {
            margin-bottom: 1rem;
        }

        #chat-log {
            flex: 1;
            border: 1px solid #ccc;
            overflow: auto;
            padding: 0.5rem;
        }

        /* Message styling */
        .message {
            margin-bottom: 0.5rem;
        }

        .my-message {
            color: red;
        }

        .other-message {
            color: blue;
        }

        .input-row {
            display: flex;
            margin-top: 1rem;
        }

        #message-input {
            flex: 1;
            padding: 0.5rem;
        }

        #send-btn {
            padding: 0.5rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>

<h1>Willkommen, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

<div class="chat-layout">
    <div class="left-pane">
        <div class="search-bar">
            <input type="text" id="user-search" placeholder="Suchen..."/>
            <button id="search-button">Suchen</button>
            <button id="start-new-chat-btn">Neuen Chat starten</button>
        </div>
        <div id="new-chat-section" style="display:none;">
            <input type="text" id="new-chat-username" placeholder="Benutzername eingeben...">
            <button id="search-new-chat-btn">Suche</button>
            <div id="new-chat-results"></div>
        </div>
        <div id="chat-list"></div>
    </div>

    <div class="right-pane">
        <div class="chat-header">
            <h2>Nachricht an: <span id="chat-partner-name">[Bitte wähle einen Chat]</span></h2>
        </div>

        <div id="chat-log"></div>

        <div class="input-row">
            <input type="text" id="message-input" placeholder="Type your message..."/>
            <button id="send-btn">Send</button>
        </div>
    </div>

</div>

<script>
    const currentUserId = "<?= $currentUserId; ?>";
    const currentUsername = "<?= $currentUsername; ?>";
    const sessionId = "<?= $sessionId; ?>";

    const socketUrl = "ws://localhost:8080?session_id=" + sessionId;
    const connection = new WebSocket(socketUrl);

    const chatListEl = document.getElementById("chat-list");
    const chatPartnerNameEl = document.getElementById("chat-partner-name");
    const chatLogEl = document.getElementById("chat-log");
    const messageInput = document.getElementById("message-input");
    const sendBtn = document.getElementById("send-btn");
    const userSearchInput = document.getElementById("user-search");
    const searchButton = document.getElementById("search-button");
    const startNewChatBtn = document.getElementById("start-new-chat-btn");
    const newChatSection = document.getElementById("new-chat-section");
    const newChatUsernameInput = document.getElementById("new-chat-username");
    const searchNewChatBtn = document.getElementById("search-new-chat-btn");
    const newChatResults = document.getElementById("new-chat-results");

    let activeChatUserId = null;
    let activeChatUsername = null;

    let messagesByUser = {};
    let chatListData = [];

    connection.onopen = function () {
        console.log("WebSocket connection established.");

        loadExistingChats();
    };

    connection.onmessage = function (event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch (err) {
            console.log("Received non-JSON message, ignoring:", event.data);
            return;
        }

        let chatUserId = (data.fromUserId === parseInt(currentUserId))
            ? data.toUserId
            : data.fromUserId;

        if (!messagesByUser[chatUserId]) {
            messagesByUser[chatUserId] = [];
        }
        messagesByUser[chatUserId].push(data);

        if (data.toUserId === parseInt(currentUserId)) {
            let fromUserName = data.fromUserName || "User " + data.fromUserId;

            upsertChatListItem(data.fromUserId, fromUserName, '');
        }

        if (activeChatUserId === chatUserId) {
            renderChatLog(chatUserId);
        }
    };


    connection.onerror = function (error) {
        console.log("WebSocket Error: ", error);
    };

    sendBtn.addEventListener("click", function () {
        const message = messageInput.value.trim();
        if (!message || !activeChatUserId) return;

        let msgObj = {
            fromUserId: currentUserId,
            fromUserName: currentUsername,
            toUserId: activeChatUserId,
            text: message
        };

        connection.send(JSON.stringify(msgObj));

        if (!messagesByUser[activeChatUserId]) {
            messagesByUser[activeChatUserId] = [];
        }
        messagesByUser[activeChatUserId].push(msgObj);

        renderChatLog(activeChatUserId);

        messageInput.value = "";
    });

    messageInput.addEventListener("keyup", function (e) {
        if (e.key === "Enter") {
            sendBtn.click();
        }
    });

    searchButton.addEventListener("click", () => {
        const query = userSearchInput.value.toLowerCase().trim();
        if (!query) return;

        fetch("search_user.php?query=" + encodeURIComponent(query))
            .then(res => res.json())
            .then(users => {
                chatListEl.innerHTML = "";
                users.forEach(u => {
                    const div = document.createElement("div");
                    div.classList.add("chat-list-item");
                    div.innerHTML = `
                        <div class="chat-username">${u.username}</div>
                        <div class="chat-last-date"></div>
                    `;
                    div.addEventListener("click", () => openChatWithUser(u.id, u.username));
                    chatListEl.appendChild(div);
                });
            })
            .catch(err => console.error("Search error:", err));
    });

    function openChatWithUser(userId, username) {
        activeChatUserId = userId;
        activeChatUsername = username;
        chatPartnerNameEl.textContent = username;

        if (messagesByUser[userId]) {
            renderChatLog(userId);
        } else {
            fetch("load_conversation.php?user_id=" + userId)
                .then(res => res.json())
                .then(msgs => {
                    messagesByUser[userId] = [];
                    msgs.forEach(m => {
                        messagesByUser[userId].push({
                            fromUserId: m.from_user_id,
                            fromUserName: (m.from_user_id === currentUserId) ? currentUsername : username,
                            toUserId: m.to_user_id,
                            text: m.message
                        });
                    });
                    renderChatLog(userId);
                })
                .catch(err => console.error("Load conversation error:", err));
        }
    }

    function renderChatLog(userId) {
        chatLogEl.innerHTML = "";
        const msgs = messagesByUser[userId] || [];

        msgs.forEach(msg => {
            let isMe = (msg.fromUserId === currentUserId);
            const div = document.createElement("div");
            div.classList.add("message", isMe ? "my-message" : "other-message");
            div.textContent = `${msg.fromUserName}: ${msg.text}`;
            chatLogEl.appendChild(div);
        });

        chatLogEl.scrollTop = chatLogEl.scrollHeight;
    }

    function loadExistingChats() {
        fetch("load_chats.php")
            .then(res => res.json())
            .then(chats => {
                chatListEl.innerHTML = "";
                chats.forEach(item => {
                    const div = document.createElement("div");
                    div.classList.add("chat-list-item");
                    div.innerHTML = `
                       <div class="chat-username">${item.username}</div>
                       <div class="chat-last-date">${item.lastMessageDate || ''}</div>
                    `;

                    div.addEventListener("click", () => openChatWithUser(item.userId, item.username));
                    chatListEl.appendChild(div);
                });
            })
            .catch(err => console.error("Load chats error:", err));
    }


    startNewChatBtn.addEventListener("click", () => {
        if (newChatSection.style.display === "none") {
            newChatSection.style.display = "block";
        } else {
            newChatSection.style.display = "none";
        }
    });

    searchNewChatBtn.addEventListener("click", () => {
        let query = newChatUsernameInput.value.trim();
        if (!query) return;

        fetch("search_user.php?query=" + encodeURIComponent(query))
            .then(res => res.json())
            .then(users => {
                newChatResults.innerHTML = "";
                if (users.length === 0) {
                    newChatResults.textContent = "Keine Nutzer gefunden.";
                } else {
                    users.forEach(u => {
                        const div = document.createElement("div");
                        div.textContent = u.username;
                        div.style.cursor = "pointer";
                        div.addEventListener("click", () => {
                            openChatWithUser(u.id, u.username);
                            newChatSection.style.display = "none";
                        });
                        newChatResults.appendChild(div);
                    });
                }
            })
            .catch(err => {
                console.error("User search error:", err);
            });
    });

    function upsertChatListItem(userId, username, lastMessageDate = '') {
        let existingItem = chatListData.find(item => item.userId === userId);
        if (existingItem) {
            existingItem.lastMessageDate = lastMessageDate || existingItem.lastMessageDate;
            existingItem.username = username || existingItem.username;
        } else {
            chatListData.push({
                userId: userId,
                username: username,
                lastMessageDate: lastMessageDate
            });
        }

        renderChatList();
    }

    function renderChatList() {
        chatListEl.innerHTML = "";
        chatListData.forEach(item => {
            const div = document.createElement("div");
            div.classList.add("chat-list-item");
            div.innerHTML = `
              <div class="chat-username">${item.username}</div>
              <div class="chat-last-date">${item.lastMessageDate || ''}</div>
            `;
            div.addEventListener("click", () => openChatWithUser(item.userId, item.username));
            chatListEl.appendChild(div);
        });
    }

</script>
</body>
</html>
