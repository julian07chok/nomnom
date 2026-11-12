import { createApp } from "vue";
import router from "./router";
import App from "./App.vue";

// Bootstrap CSS
import "bootstrap/dist/css/bootstrap.min.css";
// Bootstrap Icons
import "bootstrap-icons/font/bootstrap-icons.css";
// Bootstrap JS (for modals, dropdowns, etc.)
import "bootstrap/dist/js/bootstrap.bundle.min.js";

import "./assets/css/style.css";

createApp(App).use(router).mount("#app");
