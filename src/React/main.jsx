// cherche le conteneur racine existant et y monte React
import { createRoot } from 'react-dom/client'
import App from './App'

// la coquille Twig doit contenir <div id="root"></div>
const root = document.getElementById('root')
if (root) createRoot(root).render(<App />)
