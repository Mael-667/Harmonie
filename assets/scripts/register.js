let fileInput = document.getElementById("registration_form_pdp");
fileInput.addEventListener("change", () => {
    fileInput.previousSibling.style.background = `url(${URL.createObjectURL(fileInput.files[0])})`;
    fileInput.previousSibling.style.color = `#ffffff00`;
})

const ws = new WebSocket("ws://127.0.0.1:8080");

ws.onopen = (e) => {
  const token = getCookie("token");
  let message = {
    "type": "authentification",
    "token": token
  }
  ws.send(JSON.stringify(message));
};

ws.onclose = (e) => {
  console.log("deconnecté");
  
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