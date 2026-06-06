import { createPortal } from "react-dom";

export default function Modal({ id, onClose, children }) {
    return createPortal(
        <div className="popupBackground" onClick={onClose}>
            <div id={id} className="popup" onClick={(e) => e.stopPropagation()}>
                {children}
            </div>
        </div>,
        document.body            // ← rendu ici, hors du #side_panel
    );
}