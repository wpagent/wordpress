import { createApp } from 'vue';
import App from './App.vue';
import './../css/tailwind.css';

function initializeWPAgent() {
    const wpAgentRoot = document.getElementById('wp-agent-root');
    if (wpAgentRoot) {
        const app = createApp(App);
        app.mount('#wp-agent-root');
    } else {
        console.error('WP Agent: Unable to find #wp-agent-root element');
    }
}

if (window.wp && wp.domReady) {
    wp.domReady(initializeWPAgent);
} else {
    document.addEventListener('DOMContentLoaded', initializeWPAgent);
}
