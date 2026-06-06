import { useEffect, useRef } from "react";

export default function useWebsocket({ url, onReceiveMessage, onEditMessage, onDeleteMessage }) {

  const ws = useRef(null);

  // Les callbacks changent à chaque render (ils capturent messages/channelId).
  // On les garde à jour dans un ref pour ne PAS recréer la socket à chaque fois.
  const handlersRef = useRef(null);
  useEffect(() => {
    handlersRef.current = { onReceiveMessage, onEditMessage, onDeleteMessage };
  });

  useEffect(() => {
    let closedByUs = false;
    let reconnectTimer;

    function connect() {
      // La socket n'est créée qu'ici, et la reconnexion rappelle connect() pour tout ré-attacher.
      ws.current = new WebSocket(`ws://${url}:443`);

      ws.current.onopen = () => {
        const token = getCookie("token");
        let message = {
          "type": "authentification",
          "token": token
        }
        ws.current.send(JSON.stringify(message));
      };

      ws.current.onclose = () => {
        if (closedByUs) return;            // fermeture volontaire (démontage/url) → pas de reconnexion
        console.log("Connexion perdue, reconnexion...");
        reconnectTimer = setTimeout(connect, 2000);   // re-crée ET ré-attache tout
      };

      ws.current.onmessage = (e) => {
        console.log(e.data);
        const data = JSON.parse(e.data);   // { type, payload } : le type est sur l'objet EXTÉRIEUR
        const h = handlersRef.current;
        // TODO: CRSF TOKEN ICI AUSSI
        switch (data.type) {               // ← data.type, PAS data.payload.type (qui n'existe pas)
          case "newMessage":
            h?.onReceiveMessage(data.payload);
            break;
          case "editMessage":
            h?.onEditMessage(data.payload);
            break;
          case "deleteMessage":
            h?.onDeleteMessage(data.payload);
            break;
          default:
            break;
        }
      };

      function getCookie(cname) {
        let name = cname + "=";
        let decodedCookie = decodeURIComponent(document.cookie);
        let ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
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

    }
    
    connect();

    return () => {
      closedByUs = true;
      clearTimeout(reconnectTimer);
      ws.current?.close();
    }
  }, [url])

  return { send: (data) => ws.current?.send(data) };
}