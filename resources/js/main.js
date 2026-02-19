// Import jQuery and attach to global scope
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

// Axios for API requests
import axios from 'axios';
window.axios = axios;
const rawAppUrl = (import.meta.env.VITE_APP_URL || window.location.origin).trim();
const resolvedAppUrl = new URL(rawAppUrl, window.location.origin);
axios.defaults.baseURL = resolvedAppUrl.toString().replace(/\/+$/, '');
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrf = document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

// jQuery validation
import 'jquery-validation';

// DataTables
import DataTable from 'datatables.net-dt';
window.DataTable = DataTable;

// Bootstrap (ES module import)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// AdminLTE
import 'admin-lte/dist/js/adminlte.js';

// Your helpers
import '../../public/assets/js/helpers.js';
