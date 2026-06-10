import { useContext, useEffect, useRef, useState } from "react";
import useWebsocket from "./hooks/useWebsocket";
import MessageInput from "./MessageInput";
import { UserContext } from "./modules/UserContext";
import { usePermission } from "./hooks/usePermission";
import { FormPost } from "./modules/FormComponents";

export default function Chat({ channelId, initialMessages, setUpdate }) {
  const url = (window.location.origin).replace(window.location.protocol, "").replace("//", "");
  const user = useContext(UserContext);
  const messageConteneurRef = useRef(null);

  const [lastMessage, setLastMessage] = useState(null)
  
  const [messages, setMessages] = useState(initialMessages);
  const [loadedMessages, setLoadedMessages] = useState(initialMessages);
  // l'état temps réel des messages vit ici (et non dans App) : les nouveaux
  // messages WS ne re-rendent plus toute l'app. On resynchronise sur la prop
  // quand App recharge les messages (navigation), via le pattern recommandé
  // par React : ajustement pendant le render plutôt que dans un useEffect
  // (évite les renders en cascade — cf. "You Might Not Need an Effect").
  if (initialMessages !== loadedMessages) {
    setLoadedMessages(initialMessages);
    setMessages(initialMessages);
  }

  // utilise les callback pour queue les modifs au cas où on reçoit 2 messages entre 2 render
  const ws = useWebsocket({
    url: url,
    onReceiveMessage: (message) => {
      if (message.channel == channelId) {
        setMessages(m => [...m, message])
        setLastMessage(message);
      }
    },
    onEditMessage: (message) => {
      if (message.channel == channelId) {
        setMessages((m) => rewriteMessage(m, message));
      }
    },
    onDeleteMessage: (message) => {
      setMessages(m => m.filter(msg => msg.id != message.id))
    },
    onSpecialMessage: (message) => {
      let type = message.type;
      switch (type) {
        case "updateServer":
          setUpdate(m => m+1);
          break;
        default:
          break;
      }
    }
  });

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


  return <div id="main">
    <div id="header">
      <h3 id="channel-name"></h3>
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
  </div>
}

function Message({ user, message, firstOfAuthor, handleEdit, onDelete }) {
  const origin = window.location.origin;
  const dateString = new Date(message.timestamp.date).toLocaleTimeString();
  const hasAttachment = message.attachment !== "" && message.attachment != null;
  const isOwn = message.authorId == user?.id;
  const { Permission, hasServerRight } = usePermission();
  const canDelete = isOwn || hasServerRight(Permission.Delete);


  const [editing, setEditing] = useState(false);

  return (
    <div className="message" data-user-id={message.authorId}>
      <div className="msgSideContent" data-user-id={message.authorId}>
        {firstOfAuthor && <img
          src={`${origin}/uploads/pdp/${message.authorAvatar}`}
          alt={`Avatar de ${message.authorPseudo}`}
          className="pfpBox"
        />}
      </div>

      <div className="messageContent">
        {firstOfAuthor && <div className="messageInfo"> <span className="pseudo" data-user-id={message.authorId}>
          {message.authorPseudo}
        </span>{" "}
          <span className="messageDate">{dateString}</span>
        </div>}

        <div className="contentBox">
          <pre className="content">
            {!editing ? (
              <MessageContent hasAttachment={hasAttachment} content={message.content} attachment={message.attachment} />
            ) : (
              <EditMessage handleEdit={handleEdit} content={message.content} messageId={message.id} cancelEdit={() => setEditing(false)} />
            )
            }
          </pre>
          {canDelete && <div className="actionButtons">
            {isOwn && (
              <button className="actionButton" aria-label="Modifier le message" onClick={() => setEditing(true)}>
                <i className="fa-solid fa-pen" aria-hidden="true"></i>
              </button>
            )}
            {canDelete && (
              <button className="actionButton" aria-label="Supprimer le message" onClick={() => onDelete(message.id)}>
                <i className="fa-solid fa-trash-can" aria-hidden="true"></i>
              </button>
            )}
          </div>}
        </div>
      </div>
    </div>
  );
}
function MessageContent({ hasAttachment, content, attachment }) {
  return <>
    {hasAttachment && (
      <img
        className="attachment"
        src={`${origin}/uploads/attachments/${attachment}`}
      />
    )}
    {content}
  </>
}

function EditMessage({ handleEdit, content, cancelEdit, messageId }) {
  return <>
    <FormPost className="editForm" aria-label="Modifier le message"
      onSubmit={(e) => { e.preventDefault(); handleEdit(new FormData(e.currentTarget), messageId); cancelEdit(); }}>
      <input
        name="editedMessage"
        defaultValue={content}   /* ex-`editInput.value = oldContent` */
        placeholder="Votre nouveau message ici."
        aria-label="Nouveau contenu du message"
        autoFocus
        className="editedMessage"
      />
      <div className="horizontalLine"></div>
      <button type="submit" className="transparentButton editButton" aria-label="Envoyer la modification">
        <i className="fa-solid fa-paper-plane" aria-hidden="true"></i>
      </button>
    </FormPost>
    <div className="cancelDiv">
      <button type="button" className="textButton" onClick={cancelEdit}>
        Annuler
      </button>
    </div>
  </>
}