import { useContext, useEffect, useRef, useState } from "react";
import { WebsocketContext } from "./modules/WebsocketProvider";
import MessageInput from "./MessageInput";
import { UserContext } from "./modules/UserContext";
import Message from "./Message";

export default function Chat({ channelId, initialMessages, setUpdate, channel }) {
  const user = useContext(UserContext);
  const ws = useContext(WebsocketContext);
  const messageConteneurRef = useRef(null);


  const [lastMessage, setLastMessage] = useState(null)

  // l'état temps réel des messages vit ici (et non dans App) : les nouveaux
  // messages WS ne re-rendent plus toute l'app. On resynchronise sur la prop
  // quand App recharge les messages (navigation), via le pattern recommandé
  // par React : ajustement pendant le render plutôt que dans un useEffect
  // (évite les renders en cascade — cf. "You Might Not Need an Effect").
  const [messages, setMessages] = useState(initialMessages);
  const [loadedMessages, setLoadedMessages] = useState(initialMessages);

  if (initialMessages !== loadedMessages) {
    setLoadedMessages(initialMessages);
    setMessages(initialMessages);
  }

  function scrollToBottom(conteneur) {
    if (!conteneur) return;
    conteneur.scrollTop = conteneur.scrollHeight;          // immédiat : texte + images en cache
    conteneur.querySelectorAll("img").forEach((img) => {   // re-scroll quand une image agrandit le conteneur
      if (!img.complete) {
        img.addEventListener("load", () => { conteneur.scrollTop = conteneur.scrollHeight; }, { once: true });
      }
    });
  }
  useEffect(() => {
    scrollToBottom(messageConteneurRef.current);
  }, [])

  useEffect(() => {
    scrollToBottom(messageConteneurRef.current);
  }, [lastMessage, channelId])

  function rewriteMessage(messageList, message) {
    const editedMessages = messageList.map(msg => {
      if (msg.id == message.id) {
        return {
          ...msg,
          ...message
        }
      } else {
        return msg;
      }
    })

    return editedMessages;
  }


  useEffect(() => {
    // utilise les callback pour queue les modifs au cas où on reçoit 2 messages entre 2 render
    ws.addListener("newMessage", (message) => {
      if (message.channel == channelId) {
        setMessages(m => [...m, message])
        setLastMessage(message);
      }
    })

    ws.addListener("editMessage", (message) => {
      if (message.channel == channelId) {
        setMessages((m) => rewriteMessage(m, message));
      }
    })

    ws.addListener("deleteMessage", (message) => {
      setMessages(m => m.filter(msg => msg.id != message.id))
    })

    ws.addListener("updateServer", () => setUpdate(m => m + 1))
  }, [channelId, setUpdate, ws])



  function handleEdit(formData, messageId) {
    const newMessage = formData.get("editedMessage").trim();
    if (newMessage != "") {
      // Pas de preshot optimiste
      // oldContent = newMessage;
      const message = {
        "type": "messageEdit",
        "channel": channelId,
        "messageId": messageId,
        "content": newMessage,
        "attachment": ""
      }
      ws.send(JSON.stringify(message));
    }
  }

  function deleteMessage(messageId) {
    const message = {
      "type": "messageDelete",
      "messageId": messageId
    }
    ws.send(JSON.stringify(message));
  }


  return <section id="main" aria-label="Conversation">
    <div id="header">
      <span id="channel-name">{channel && `#${channel?.name}`}</span>
    </div>

    <div id="messages" ref={messageConteneurRef}>
      {/* TODO: utiliser useMemo pour sauvegarder les messages render*/}
      {messages?.map((msg, i) => (
        <Message
          key={msg.id}
          message={msg}
          firstOfAuthor={i === 0 || messages[i - 1].authorId !== msg.authorId}
          handleEdit={handleEdit}
          onDelete={deleteMessage}
          user={user}
        />
      ))}
    </div>


    <MessageInput ws={ws} channelId={channelId} />
  </section>
}
