import { ref, onMounted, onUnmounted } from 'vue'

export function useIsPwa() {
  const isPwa = ref(false)

  const checkIsPwa = () => {
    if (typeof window === 'undefined') return false

    const standaloneDisplay =
      window.matchMedia?.('(display-mode: standalone)').matches

    const iosStandalone = (window.navigator?.standalone === true) ?? false

    const displayModeFromReferrer =
      document.referrer?.startsWith?.('android-app://')

    return standaloneDisplay || iosStandalone || displayModeFromReferrer
  }

  onMounted(() => {
    isPwa.value = checkIsPwa()

    // React to display-mode changes
    const mediaQuery = window.matchMedia('(display-mode: standalone)')
    const listener = () => {
      isPwa.value = checkIsPwa()
    }

    mediaQuery.addEventListener('change', listener)

    onUnmounted(() => {
      mediaQuery.removeEventListener('change', listener)
    })
  })

  return {
    isPwa
  }
}
