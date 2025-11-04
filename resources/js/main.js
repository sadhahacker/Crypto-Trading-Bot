// Import jQuery and attach to global scope
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

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
