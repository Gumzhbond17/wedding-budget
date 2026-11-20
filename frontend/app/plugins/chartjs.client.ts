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
  // Canvas text does not inherit CSS, so repeat the app font (it covers Lao category names too).
  Chart.defaults.font.family = '\'Google Sans\', sans-serif'
})
