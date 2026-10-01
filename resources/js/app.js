import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

import './echo';
import './pwa';
