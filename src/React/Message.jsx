import { useState } from "react";
import { usePermission } from "./hooks/usePermission";
import { FormPost } from "./modules/FormComponents";

export default function Message({ user, message, firstOfAuthor, handleEdit, onDelete, key }) {
  const origin = window.location.origin;
  const dateString = new Date(message.timestamp.date).toLocaleTimeString();
  const hasAttachment = message.attachment !== "" && message.attachment != null;
  const isOwn = message.authorId == user?.id;
  const { Permission, hasServerRight } = usePermission();
  const canDelete = isOwn || hasServerRight(Permission.Delete);


  const [editing, setEditing] = useState(false);

  return (
    <div className="message" data-user-id={message.authorId} key={key}>
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
          {canDelete && (handleEdit || onDelete) && <div className="actionButtons">
            {isOwn && handleEdit && (
              <button className="actionButton" aria-label="Modifier le message" onClick={() => setEditing(true)}>
                <i className="fa-solid fa-pen" aria-hidden="true"></i>
              </button>
            )}
            {canDelete && onDelete && (
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
  const origin = window.location.origin;
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
