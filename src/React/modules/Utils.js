export function copy(content) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(content);
    } else {
        // contexte non sécurisé (HTTP hors localhost) : fallback
        const ta = document.createElement("textarea");
        ta.value = content;
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        ta.remove();
    }
}
