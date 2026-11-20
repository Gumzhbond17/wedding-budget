import type { ChartPalette } from '~/utils/dashboard'

/** Chart colours for the current colour mode; charts redraw when the mode changes. */
export function useChartTheme(): ComputedRef<ChartPalette> {
  const colorMode = useColorMode()

  return computed(() => chartPalette(colorMode.value === 'dark'))
}
