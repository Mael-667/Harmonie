import { createContext, useEffect, useMemo, useRef } from "react";

export const WebsocketContext = createContext(null);

export default function WebsocketProvider({ url, children }) {

  const ws = useRef(null);

  // Les callbacks changent à chaque render (ils capturent messages/channelId).
  // On les garde à jour dans un ref pour ne PAS recréer la socket à chaque fois.
  const callbacksRef = useRef({});

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
        const callback = callbacksRef.current[data.type]
        if(callback){
          callback(data.payload);
        } else {
          console.log(data);
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

  const value = useMemo(() => ({ send: (data) => ws.current?.send(data), addListener: (event, callback) => callbacksRef.current[event] = callback}), [])

  return <WebsocketContext value={value}>
    {children}
  </WebsocketContext>
}