import { useState } from "react";
import { FormPost } from "./modules/FormComponents";

export default function MessageInput({ ws, channelId }) {
  async function sendMessage(formData) {
    const textContent = formData.get("message").trim();
    // les formulaires fonctionnent par clé valeur où leur clé est le nom du champ
    let file = formData.get("attachment");
    let attachment = "";

    if (file.size != 0) {
      formData.append("channel", channelId);
      let response = await fetch(origin + "/app/fileUpload", {
        method: "POST",
        body: formData,
      })
      response = await response.json();
      attachment = response.fileName;
    }

    if (textContent == "" && file.size == 0) {
      // TODO: retour d'empechage d'envoi message vide
      return false;
    }

    const message = {
      "type": "message",
      "channel": channelId,
      "content": textContent,
      "attachment": attachment
    }

    ws.send(JSON.stringify(message));
    // TODO: Vérifier que la data a bien été reçue
    return true;
  }


  // Todo bouton supprimer l'image en preview
  const [attachment, setAttachment] = useState(null);
  function updatePreview(attachment) {
    setAttachment(attachment ? URL.createObjectURL(attachment) : null);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const sent = await sendMessage(new FormData(form));
    if (sent) {                 // on ne vide le champ que si l'envoi a réussi
      form.reset();
      setAttachment(null);
    }
  }

  return <div id="input">
    {attachment && <AttachmentPreview attachmentUrl={attachment} />}
    <FormPost id="msgForm" method="post" onSubmit={handleSubmit}>
      <label htmlFor="attachment" id="attachmentButton" aria-label="Joindre un fichier">
        <i className="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
        <input type="file" name="attachment" id="attachment" onChange={e => {updatePreview(e.target.files[0])}} />
      </label>
      <div className="horizontalLine"></div>
      <label htmlFor="message" id="messageLabel" aria-label="Saisir un message">
        <input type="text" name="message" id="message" placeholder="Écrivez ici" autoComplete="off" minLength={1} maxLength={2000} />
      </label>
      <button type="button" id="emoji-btn" aria-label="Ouvrir le sélecteur d'émojis">
        <i className="fa-regular fa-face-laugh-beam" aria-hidden="true"></i>
      </button>
      <div className="horizontalLine"></div>
      <button type="submit" id="submitButton">
        <i className="fa-solid fa-paper-plane" aria-hidden="true"></i>
      </button>
    </FormPost>
  </div>
}

function AttachmentPreview({ attachmentUrl }) {
  return <div className="attachmentPreviewDiv">
    <img src={attachmentUrl} className="attachmentPreview" alt="Aperçu de la pièce jointe" />
  </div>
}