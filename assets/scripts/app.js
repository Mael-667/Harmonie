const url = (window.location.origin).replace(window.location.protocol, "");
const origin = window.location.origin;


// WEBSOCKET SECTION
let ws = new WebSocket(`ws:${url}:443`);

ws.onopen = (e) => {
  const token = getCookie("token");
  let message = {
    "type": "authentification",
    "token": token
  }
  ws.send(JSON.stringify(message));
};

ws.onclose = (e) => {
  console.log("Connexion perdue, reconnexion...");

  let recoInterval = setInterval(() => {
    if(ws.readyState == 1){
      console.log("Connecté.");
      clearInterval(recoInterval);
    } else {
        ws = new WebSocket(`ws:${url}:443`);
    }
  }, 2000)
};

ws.onmessage = (e) => {
  console.log(e.data);
  //TODO: vérifier que l'utilisateur est bien dans le chan en question avant d'update 
  let message = JSON.parse(e.data);
  if(message.channel == currentChannelId){
    renderMessage();
  }
};

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(';');
  for(let i = 0; i <ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}


const form = document.getElementById("msgForm");
form.addEventListener("submit", (e) => {
  e.preventDefault();
  const messageElmt = document.getElementById("message");

  const textContent = messageElmt.value;

  const message = {
    "type" : "message",
    "channel": currentChannelId,
    "content": textContent,
    "attachment": ""
  }

  ws.send(JSON.stringify(message));
  // TODO: Vérifier que la data a bien été reçue
  messageElmt.value = "";
})




// POPUP SECTION
const popupBg = document.querySelector(".popupBackground");
let openedPopups = [];
document.getElementById("newServer").addEventListener("click", (e) =>{
  popupBg.style.display = "flex";
  let popup = document.getElementById("popupNewServer");
  popup.style.display = "flex";
  openedPopups.push(popup);
})

popupBg.addEventListener("click", (e) =>{
  e.stopPropagation();
  let lastPopup = openedPopups.pop();
  lastPopup.style.display = "none";
  if(openedPopups.length == 0) popupBg.style.display = "none";
})


document.querySelectorAll(".popup").forEach((e) => {
  e.addEventListener('click', (f) =>{
    f.stopPropagation();
  })
})


// new server popup logic
let fileLabel = document.getElementById("serverIconLabel");
let fileInput = document.getElementById("server_icon");
fileInput.addEventListener("change", () => {
    fileLabel.style.background = `url(${URL.createObjectURL(fileInput.files[0])})`;
    fileLabel.style.color = `#ffffff00`;
})

let formNewServer = document.querySelector("#createServer>form")
formNewServer.addEventListener("submit", (e) => {
    e.preventDefault();

    let form = new FormData(formNewServer);
     fetch(origin+"/app/newServer", {
        method: "POST",
        // Set the FormData instance as the request body
        body: form,
      })
      .then((response) => response.json())
      .then((e) =>{
        console.log(e);
        // TODO: refresh les serveurs si tout est bon
      })
      .catch((err) => console.log(err))
    
})





// SERVER DATA LOADING SECTION
let currentServerId = 0;
let currentChannelId = 0;
// Get user's servers
function getServers(){
  fetch(origin+"/app/getServers")
  .then((response) => response.json())
  .then((e) => {
    const serverList = document.getElementById('serverList');
    serverList.replaceChildren();  
    let renderedServerButton = [];
    e.forEach((element) => {
      renderedServerButton.push(renderServerButton(element));
    })

    serverList.prepend(...renderedServerButton);
  })
  .catch((err) => console.log(err))
}

function renderServerButton(server){
  let button = document.createElement("button");
  button.classList.add("button", "serverButton");
  button.dataset.serverId = server.id;
  button.setAttribute("aria-label", `Click to join the server : ${server.name}" serverId="${server.id}`);
  button.style.backgroundImage = `url(${origin}/${server.icon})`
  
  button.addEventListener("click", (e) =>{
    displayServer(server.id);
  })

  return button;
}

function displayServer(id, channelId = -1){
  // TODO: pouvoir customiser l'ordre des serveurs
  let channelUrl = channelId > 0 ? "/"+channelId : "" ;
  currentServerId = id;
  window.history.pushState("", "", origin+"/app/"+id+channelUrl);
  fetch(origin+"/app/"+id+channelUrl, {
    headers: { "Accept": "application/json" }
  })
    .then((body) => body.json())
    .then((json) => {
      renderChannelsButtons(json.channels);
      displayMessages(json.messages);
      currentChannelId = json.currentChannel;

      let prevServFocus = document.querySelector('.serverButtonActive');
      if(prevServFocus) prevServFocus.classList.remove('serverButtonActive');
      let currentServerButton = document.querySelector(`[data-server-id="${currentServerId}"]`);
      currentServerButton.classList.add('serverButtonActive');
      let currentChannelButton = document.querySelector(`[data-channel-id="${currentChannelId}"]`);

      console.log(json);
    })
    .catch((err) => console.log(err));
}

function renderChannelsButtons(channels){
  let div = document.createElement("div");
  let categories = {};
  channels.forEach((channel) => {
    let chanElement = document.createElement("button");
    chanElement.dataset.channelId = channel.id;
    chanElement.classList.add('channelButton');
    chanElement.textContent = "#"+channel.name;

    chanElement.addEventListener("click", (e) => {
      displayServer(currentServerId, channel.id);
    })

    if(channel.category == null){
      div.append(chanElement);
    } else {
      // TODO: pouvoir customiser l'ordre des channels
      if(categories[channel.category] == undefined){
        categories[channel.category] = document.createElement("div");
      }
      categories[channel.category].append(chanElement);
    }
  })

  for(const key in categories){
    div.append(categories[key]);
  }

  document.getElementById("serverInfo").replaceChildren(div);
}

function displayMessages(messages){
  let messageConteneur = document.getElementById("messages");
  messageConteneur.replaceChildren();
  messages.forEach((e) => {
    let newMessage = renderMessage(e);
    if(newMessage){
      messageConteneur.append(newMessage);
    }
  })
}

function renderMessage(message){
  // TODO: Process l'attachment
  let messageConteneur = document.getElementById("messages");
  let lastMessage = messageConteneur.lastElementChild;
  if(lastMessage == null || lastMessage.dataset.userId != message.authorId){
    const messageBox = document.createElement("div");
    messageBox.classList.add("message");
    messageBox.dataset.userId = message.authorId;
  
    const pfpBox = document.createElement("div");
    pfpBox.classList.add("pfpBox");
    pfpBox.dataset.userId = message.authorId;
  
    const avatar = document.createElement("img");
    avatar.src = `${origin}/uploads/pdp/${message.authorAvatar}`;
    avatar.alt = `Avatar de ${message.authorPseudo}`;
    pfpBox.append(avatar);
  
    const messageContent = document.createElement("div");
    messageContent.classList.add("messageContent");
  
    const pseudo = document.createElement("span");
    pseudo.classList.add("pseudo");
    pseudo.dataset.userId = message.authorId;
    pseudo.textContent = message.authorPseudo;
  
    let dateString = new Date(message.timestamp.date).toLocaleTimeString();
    const date = document.createElement("span");
    date.classList.add("messageDate");
    date.textContent = dateString;
  
    const content = document.createElement("div");
    content.classList.add("content");

    const msg = document.createElement('pre');
    msg.classList.add("content");
    msg.textContent = message.content;
    content.append(msg);
  
    messageContent.append(pseudo, " ", date, content);
    messageBox.append(pfpBox, messageContent);
  
    return messageBox;
  } else {
    const msg = document.createElement('pre');
    msg.classList.add("content");
    msg.textContent = message.content;
    lastMessage.querySelector(".content").append(msg);
    return false;
  }
}


getServers();

// Charge automatiquement les info du serveur si l'utilisateur accède spécifiquement a un lien contenant id serveur et channel
const segments = window.location.pathname.split("/").filter(Boolean);
if(segments[0] == "app"){
  let serverId = segments[1];
  let channelId = segments[2];

  if(serverId != null && !isNaN(serverId)){
    if(channelId != null && !isNaN(channelId)){
      displayServer(serverId, channelId);
    } else {
      displayServer(serverId);
    }
  }
}

