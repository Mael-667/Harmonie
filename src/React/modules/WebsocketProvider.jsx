import { createContext, useEffect, useMemo, useRef } from "react";

export const WebsocketContext = createContext(null);

export default function WebsocketProvider({ url, children }) {

  const ws = useRef(null);

  // Les callbacks changent à chaque render (ils capturent messages/channelId).
  // On les garde à jour dans un ref pour ne PAS recréer la socket à chaque fois.
  const callbacksRef = useRef({});

  // File des messages émis avant l'ouverture de la socket (état CONNECTING).
  const pendingRef = useRef([]);

  useEffect(() => {
    let closedByUs = false;
    let reconnectTimer;

    function connect() {
      // La socket n'est créée qu'ici, et la reconnexion rappelle connect() pour tout ré-attacher.
      ws.current = new WebSocket(`ws://${url}:443`);

      ws.current.onopen = () => {
        console.log("Websocket connecté !");
        // Puis on rejoue les messages mis en attente avant l'ouverture.
        pendingRef.current.forEach((data) => ws.current.send(data));
        pendingRef.current = [];
      };

      ws.current.onclose = () => {
        if (closedByUs) return;            // fermeture volontaire (démontage/url) → pas de reconnexion
        console.log("Connexion perdue, reconnexion...");
        reconnectTimer = setTimeout(connect, 2000);   // re-crée ET ré-attache tout
      };

      ws.current.onmessage = (e) => {
        // console.log(e.data);
        const data = JSON.parse(e.data);   // { type, payload } : le type est sur l'objet EXTÉRIEUR
        const callback = callbacksRef.current[data.type]
        if(callback){
          callback(data.payload);
        } else {
          console.error({"Message non reconnu : ": data});
        }
      };
    }
    
    connect();

    return () => {
      closedByUs = true;
      clearTimeout(reconnectTimer);
      ws.current?.close();
    }
  }, [url])

  const value = useMemo(() => ({
    send: (data) => {
      const socket = ws.current;
      if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(data);
      } else {
        // CONNECTING (ou pas encore créée) → on bufferise, flush à l'onopen.
        pendingRef.current.push(data);
      }
    },
    addListener: (event, callback) => (callbacksRef.current[event] = callback),
  }), [])

  return <WebsocketContext value={value}>
    {children}
  </WebsocketContext>
}