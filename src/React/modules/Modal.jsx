import { createPortal } from "react-dom";

export default function Modal({ id, onClose, children, className = "" }) {
    return createPortal(
        <div className="popupBackground" onClick={onClose}>
            <div id={id} className={`popup ${className}`} onClick={(e) => e.stopPropagation()}>
                {children}
            </div>
        </div>,
        document.body            // ← rendu ici, hors du #side_panel
    );
}