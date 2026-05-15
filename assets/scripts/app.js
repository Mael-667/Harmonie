const url = (window.location.origin).replace(window.location.protocol, "");
const origin = window.location.origin;
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
    "channel": "",
    "content":  {
      "text": textContent,
      "attachment": ""
    }
  }

  ws.send(JSON.stringify(message));
  // TODO: Vérifier que la data a bien été reçue
  messageElmt.value = "";
})


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

    try {
      // console.log(origin+"/app/newServer");
      
     fetch(origin+"/app/newServer", {
        method: "POST",
        // Set the FormData instance as the request body
        body: form,
      })
      .then((response) => 
        {
          console.log(response);
          return response.json()
        })
      .then((e) =>{
        console.log(e);
        
      })
    } catch (e) {
      console.error(e);
    }
    
})

// Get user's servers
function getServers(){
  fetch(origin+"/app/getServers")
  .then((response) => response.json())
  .then((e) => {
    const serverList = document.getElementById('servers');
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
  button.setAttribute("aria-label", `Click to join the server : ${server.name}" serverId="${server.id}`);
  button.setAttribute('serverId', server.id);
  button.style.backgroundImage = `url(${server.icon})`
  
  button.addEventListener("click", (e) =>{
    displayServer(server.id);
  })

  return button;
}

function displayServer(id){
  window.history.pushState("", "", origin+"/app/"+id);
  fetch(origin+"/app/"+id)
    .then((body) => body.json())
    .then((json) => {
      console.log(json)
    })
    .catch((err) => console.log(err));
}


getServers();
