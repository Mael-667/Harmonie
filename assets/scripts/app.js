const url = (window.location.origin).replace(window.location.protocol, "");
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
document.getElementById("newServer").addEventListener("click", (e) =>{
  popupBg.style.display = "flex";
  document.getElementById("popupNewServer").style.display = "flex";
})
