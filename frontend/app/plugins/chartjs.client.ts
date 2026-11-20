import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart,
  Legend,
  LinearScale,
  Tooltip
} from 'chart.js'

/**
 * Registers only the Chart.js parts the dashboard draws (bars, doughnut, tooltips, legend),
 * once, so tree-shaking drops the rest of the library.
 */
export default defineNuxtPlugin(() => {
  Chart.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend)
  // Public Sans, then Noto Sans Lao for Lao category names, as in the rest of the app.
  Chart.defaults.font.family = '\'Public Sans\', \'Noto Sans Lao\', sans-serif'
})
