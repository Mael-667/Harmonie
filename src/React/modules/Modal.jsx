import { createPortal } from "react-dom";

// TODO: https://developer.mozilla.org/fr/docs/Web/HTML/Reference/Elements/dialog
export default function Modal({ id, onClose, children, className = "" }) {
    return createPortal(
        <div className="popupBackground" onClick={onClose}>
            <dialog id={id} className={`popup ${className}`} onClick={(e) => e.stopPropagation()}>
                {children}
            </dialog>
        </div>,
        document.body            // ← rendu ici, hors du #side_panel
    );
}