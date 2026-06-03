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
  let message = JSON.parse(e.data);
  // TODO: CRSF TOKEN ICI AUSSI
  switch (message.type) {
    case "newMessage":
      message = message.payload;
      if(message.channel == currentChannelId){
        let newMessage = renderMessage(message);
        if(newMessage){
          let messageConteneur = document.getElementById("messages");
          messageConteneur.append(newMessage);
        }
        scrollMessageConteneurToBottom();
      }
      break;
    case "editMessage":
      message = message.payload;
      if(message.channel == currentChannelId){
        rewriteMessage(message);
      }
      break;
    case "deleteMessage":
      message = message.payload;
      removeMessage(message.id);
      break;
    default:
      break;
  }
};

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(';');
  for(let i = 0; i < ca.length; i++) {
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


// Todo bouton supprimer l'image en preview
let attachmentInput = document.getElementById("attachment");
attachmentInput.addEventListener("change", () => {
  let attachment = attachmentInput.files[0];
  let previousPreview = document.querySelector('.attachmentPreviewDiv');
  
  if(previousPreview != undefined){
    previousPreview.remove();
  }

  if(attachment != undefined){
    let attachmentUrl = URL.createObjectURL(attachment);
    let attachmentPreviewDiv = document.createElement("div");
    let form = document.getElementById("msgForm");
    attachmentPreviewDiv.classList.add("attachmentPreviewDiv");

    const attachmentPreview = document.createElement("img");
    attachmentPreview.src = attachmentUrl;
    attachmentPreview.classList.add("attachmentPreview");

    attachmentPreviewDiv.prepend(attachmentPreview);                                                                                                         
    document.getElementById('input').prepend(attachmentPreviewDiv);  
  } else {
    previousPreview.remove();
  }

})



const form = document.getElementById("msgForm");
form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const messageElmt = document.getElementById("message");

  const textContent = messageElmt.value.trim();

  // les formulaires fonctionnent par clé valeur où leur clé est le nom du champ
  let formData = new FormData(form);
  let file = formData.get("attachment");
  let attachment = "";

  if(file.size != 0){
    formData.append("channel", currentChannelId);
    let response = await fetch(origin+"/app/fileUpload", {
        method: "POST",
        body: formData,
      })
    response = await response.json();
    attachment = response.fileName;
  }

   if(textContent == "" && file.size == 0){
    // TODO: retour d'empechage d'envoi message vide
    return;
  }

  const message = {
    "type" : "message",
    "channel": currentChannelId,
    "content": textContent,
    "attachment": attachment
  }

  ws.send(JSON.stringify(message));
  // TODO: Vérifier que la data a bien été reçue
  form.reset();
  let previousPreview = document.querySelector('.attachmentPreviewDiv');
  
  if(previousPreview != undefined){
    previousPreview.remove();
  }
})

let oldContent;
function editMessage(id, button){
  closeEdit();
  const contentDiv = document.querySelector(`[data-message-id="${id}"]`);
  oldContent = contentDiv.textContent;
  contentDiv.textContent = "";

  const editForm = document.createElement("form");
  editForm.classList.add('editForm');
  editForm.setAttribute("aria-label", "Modifier le message");
  // const editLabel = document.createElement("label");
  // editLabel.setAttribute("for", "editedMessage");

  const editInput = document.createElement("input");
  editInput.setAttribute("name", "editedMessage");
  editInput.setAttribute("id", "editedMessage");
  editInput.value = oldContent;
  editInput.setAttribute("placeholder", "Votre nouveau message ici.");
  editInput.setAttribute("aria-label", "Nouveau contenu du message");

  const verticalBar = document.createElement("div");
  verticalBar.classList.add("horizontalLine");

  const editButton = document.createElement("button");
  editButton.classList.add("transparentButton", "editButton");
  editButton.setAttribute("aria-label", "Envoyer la modification");
  editButton.innerHTML = `<i class="fa-solid fa-paper-plane" aria-hidden="true"></i>`;

  editForm.append(editInput);
  editForm.append(verticalBar);
  editForm.append(editButton);

  contentDiv.append(editForm);

  const cancelDiv = document.createElement("div");
  cancelDiv.classList.add("cancelDiv");

  const cancelButton = document.createElement('button');
  cancelButton.classList.add("textButton");
  cancelButton.textContent = "Annuler";

  cancelButton.addEventListener("click", () =>{
    closeEdit();
  })

  cancelDiv.append(cancelButton);

  contentDiv.append(cancelDiv);

  const messageId = contentDiv.getAttribute("data-message-id");
  editForm.addEventListener('submit', (e) =>{
    const newMessage = editInput.value.trim();
    e.preventDefault();
    if(newMessage != ""){
      // Pas de preshot optimiste
      // oldContent = newMessage;
      const message = {
        "type" : "messageEdit",
        "channel": currentChannelId,
        "messageId": messageId,
        "content": newMessage,
        "attachment": ""
      }
      ws.send(JSON.stringify(message));

    }
    closeEdit();
  })
}

function deleteMessage(messageId){
  const message = {
    "type" : "messageDelete",
    "messageId": messageId
  }
  ws.send(JSON.stringify(message));
}


function closeEdit(){
  const editForm = document.querySelector(".editForm");
  if(editForm){
    let message = editForm.parentElement;
    // Apparemment ça enleve les eventlistener des elements enfants aussi donc ça laisse un état propre
    message.replaceChildren();
    message.textContent = oldContent;
    let messageId = message.getAttribute("data-message-id");
    addActionButtons(message, messageId);
    editForm.remove();
  }
}



// POPUP SECTION
class PopupManager{
  constructor(background){
    this.popupBg = background;
    this.openedPopups = [];

        
    this.popupBg.addEventListener("click", (e) =>{
      e.stopPropagation();
      let lastPopup = this.openedPopups.pop();
      lastPopup.style.display = "none";
      if(this.openedPopups.length == 0) this.popupBg.style.display = "none";
    })
  }

  add(buttonId, popupId){
    document.getElementById(buttonId).addEventListener("click", (e) =>{
      this.popupBg.style.display = "flex";
      let popup = document.getElementById(popupId);
      popup.addEventListener('click', (f) =>{
        f.stopPropagation();
      })
      popup.style.display = "flex";
      this.openedPopups.push(popup);
    })
  }
}

let popupManager = new PopupManager(document.querySelector(".popupBackground"))
popupManager.add("newServer", "popupNewServer");
popupManager.add("editServer", "popupServerSettings")


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
      // TODO: refresh le form apres
      let lastPopup = openedPopups.pop();
      lastPopup.style.display = "none";
      if(openedPopups.length == 0) popupBg.style.display = "none";
      getServers();
    })
    .catch((err) => console.log(err))
    
})

// edit server popup logic
document.getElementById("editServer").addEventListener("click", (e) =>{
  fetch(origin+"/app/getInvitId")
    .then((response) => response.json())
    .then((e) =>{
      document.getElementById("newInvit").value = e.randomId;
    });
})

let newInvitForm = document.getElementById("newInvitForm");
newInvitForm.addEventListener("submit", (e) => {
  e.preventDefault();

  let form = new FormData(newInvitForm);
  form.append("serverId", currentServerId);
  form.append("expirationDate", (Date.now()) + 99999999999);

  fetch(origin+"/app/newInvit", {
    method: "POST",
    body: form
  })
  .then((response) => response.json())
  .then((e) => {

  })
  .catch({
    // indiquer a l'utilisateur que l'id est deja pris
  })

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
      currentChannelId = json.currentChannel;
      PermissionManager.updateRoles(json.roles);
      renderChannelsButtons(json.channels);
      displayMessages(json.messages);
      updateServerDetails(json.serverId, json.serverName);

      let prevServFocus = document.querySelector('.serverButtonActive');
      if(prevServFocus) prevServFocus.classList.remove('serverButtonActive');
      let currentServerButton = document.querySelector(`[data-server-id="${currentServerId}"]`);
      currentServerButton.classList.add('serverButtonActive');
      let currentChannelButton = document.querySelector(`[data-channel-id="${currentChannelId}"]`);

      console.log(json);
    })
    .catch((err) => console.log(err));
}

function updateServerDetails(serverId, serverNom){
  const serverName = document.querySelector(".serverName");
  serverName.textContent = serverNom;
}

function renderChannelsButtons(channels){
  let div = document.createElement("div");
  let categories = {};
  channels.forEach((channel) => {
    let chanElement = document.createElement("button");
    chanElement.dataset.channelId = channel.id;
    chanElement.classList.add('channelButton');
    chanElement.textContent = "#"+channel.name;
    if(channel.id == currentChannelId) chanElement.classList.add("channelButtonActive")
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

  document.getElementById("channels").replaceChildren(div);
}

function displayMessages(messages){
  let messageConteneur = document.getElementById("messages");
  if(messages.error != undefined) {
    // TODO: handle l'erreur en qst probablement acces denied
    console.log(messages);
    return;
  }
  messageConteneur.replaceChildren();
  messages.forEach((e) => {
    let newMessage = renderMessage(e);
    if(newMessage){
      messageConteneur.append(newMessage);
    }
  })
  scrollMessageConteneurToBottom();
}

function scrollMessageConteneurToBottom(){
  let messageConteneur = document.getElementById("messages");
  // les images (avatars, pièces jointes) se chargent après coup et agrandissent
  // le conteneur : on re-scrolle quand chacune a fini de charger
  messageConteneur.querySelectorAll("img").forEach((img) => {
    if(!img.complete){
      img.addEventListener("load", () => {
        messageConteneur.scrollTop = messageConteneur.scrollHeight;
      }, { once: true });
    }
  });
}

function renderMessage(message){
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
    content.classList.add("contentBox");

    
    const msg = document.createElement('pre');

    msg.classList.add("content");
    msg.textContent = message.content;
    msg.dataset.messageId = message.id;
    msg.dataset.userId = message.authorId;
    content.append(msg);
    
    if(message.attachment != "" && message.attachment != null){
      const attachment = document.createElement("img");
      attachment.classList.add("attachment");
      attachment.src = `${origin}/uploads/attachments/${message.attachment}`;
      msg.prepend(attachment);
    }

    // action on message buttons
    addActionButtons(msg, message.id);

    messageContent.append(pseudo, " ", date, content);
    messageBox.append(pfpBox, messageContent);
  
    return messageBox;
  } else {
    const msg = document.createElement('pre');
    msg.classList.add("content");
    msg.textContent = message.content;
    msg.dataset.messageId = message.id;
    msg.dataset.userId = message.authorId;

    if(message.attachment != "" && message.attachment != null){
      const attachment = document.createElement("img");
      attachment.classList.add("attachment");
      attachment.src = `${origin}/uploads/attachments/${message.attachment}`;
      // Todo: pouvoir mettre un alt a l'image lors de l'upload
      msg.prepend(attachment);
    }

    addActionButtons(msg, message.id);

    lastMessage.querySelector(".contentBox").append(msg);
    return false;
  }
}

function rewriteMessage(msg){
  let messageId = msg.id;
  const contentDiv = document.querySelector(`[data-message-id="${messageId}"]`);
  contentDiv.textContent = msg.content;
  addActionButtons(contentDiv, messageId);
}

function removeMessage(id){
  // supprime l'attachment aussi
  const contentDiv = document.querySelector(`[data-message-id="${id}"]`);
  if(contentDiv == undefined) return;
  const contentBox = contentDiv.parentElement;
  contentDiv.remove();

  if(contentBox.childElementCount == 0){
    contentBox.parentElement.parentElement.remove();
  }
}

function addActionButtons(msg, id){
  const actionButtons = document.createElement("div");
  actionButtons.classList.add("actionButtons");
  
  const authorId = msg.dataset.userId;

  // ajoute le bouton edit que si c'est le message de l'utilisateur
  if(authorId == userId){
    const editButton = document.createElement('button');
    editButton.classList.add("actionButton");
    editButton.setAttribute("aria-label", "Modifier le message");
    editButton.innerHTML = `<i class="fa-solid fa-pen" aria-hidden="true"></i>`;
    editButton.addEventListener("click", () =>{
      editMessage(id, editButton);
    })
    actionButtons.append(editButton);
  }


  // ajoute le bouton delete que si c'est le message de l'utilisateur ou si droits
  if(authorId == userId || PermissionManager.hasServerRight(Permission.Delete)){
    const deleteButton = document.createElement('button');
    deleteButton.classList.add("actionButton");
    deleteButton.setAttribute("aria-label", "Supprimer le message");
    deleteButton.innerHTML = `<i class="fa-solid fa-trash-can" aria-hidden="true"></i>`;
    deleteButton.addEventListener("click", () =>{
      deleteMessage(id);
    })
    actionButtons.append(deleteButton);
  }

  if(actionButtons.childElementCount != 0){
    msg.append(actionButtons);
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


const Permission = {
    Read: "read",
    Write: "write",
    Edit: "edit",
    Delete: "delete",
};

class PermissionManager{
  static updateRoles(roles){
    this.roles = roles;
  }

  static hasServerRight(permission = Permission.Read){
    for(let i = 0; i < this.roles.length; ++i){
      if(this.roles[i].members.includes(userId) || this.roles[i].members.includes("*")){
        if(this.roles[i].serverPermission.includes("*") || this.roles[i].serverPermission.includes(permission)){
          return true;
        }
      }
    }
    return false;
  }
}