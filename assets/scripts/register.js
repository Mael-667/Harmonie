let fileInput = document.getElementById("registration_form_pdp");
fileInput.addEventListener("change", () => {
    fileInput.previousSibling.style.background = `url(${URL.createObjectURL(fileInput.files[0])})`;
    fileInput.previousSibling.style.color = `#ffffff00`;
})

const ws = new WebSocket("ws://127.0.0.1:8080");

ws.onopen = (e) => {
  console.log(e);
  
  ws.send(`My body is made of swords.
 My blood is of iron and my heart of glass.
 Through countless battlefields undefeated.
 Not even once fleeing,
 Not even once being understood.
 He was always alone, intoxicated with victory on the hill of swords.
 Thus, this life has no meaning.
 This body was certainly made out of swords`)
};

ws.onclose = (e) => {
  console.log("deconnecté");
  
};

ws.onmessage = (e) => {
  console.log(e.data);
};