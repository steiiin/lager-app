// src/composables/useSpeech.ts
import { ref, onMounted, onBeforeUnmount } from 'vue'

export function useSpeech() {
  const isSupported = ref('speechSynthesis' in window)
  const isSpeaking = ref(false)
  const voices = ref([])

  // Load available voices (they are often loaded asynchronously)
  const loadVoices = () => {
    voices.value = window.speechSynthesis.getVoices()
  }

  onMounted(() => {
    if (isSupported.value) {
      loadVoices()
      window.speechSynthesis.onvoiceschanged = loadVoices
    }
  })

  onBeforeUnmount(() => {
    window.speechSynthesis.onvoiceschanged = null
  })

  // Find a German voice (fallback to any voice if none found)
  const getGermanVoice = () => {
    return (
      voices.value.find((voice) => voice.lang.startsWith('de-DE')) ||
      voices.value.find((voice) => voice.lang.startsWith('de')) ||
      voices.value[0] || // fallback to first available
      null
    )
  }

  /**
   * Speak a number (or any text) in German
   * @param numberOrText The number (as number or string) or any text to speak
   * @param options Optional settings
   */
  const speak = (numberOrText, options = {}
  ) => {
    if (!isSupported.value) {
      console.warn('Speech Synthesis is not supported in this browser.')
      return
    }

    window.speechSynthesis.cancel() // Stop any ongoing speech

    const text = numberOrText.toString()
    const utterance = new SpeechSynthesisUtterance(text)

    utterance.lang = options.lang ?? 'de-DE'
    utterance.rate = options.rate ?? 1
    utterance.pitch = options.pitch ?? 1
    utterance.volume = options.volume ?? 1

    const germanVoice = getGermanVoice()
    if (germanVoice) {
      utterance.voice = germanVoice
    }

    utterance.onstart = () => {
      isSpeaking.value = true
    }

    utterance.onend = () => {
      isSpeaking.value = false
    }

    utterance.onerror = () => {
      isSpeaking.value = false
    }

    window.speechSynthesis.speak(utterance)
  }

  // Convenience function specifically for numbers
  const speakNumber = (number, options = {}) => {
    speak(number, options)
  }

  // Stop current speech
  const stop = () => {
    if (isSupported.value) {
      window.speechSynthesis.cancel()
      isSpeaking.value = false
    }
  }

  return {
    isSupported,
    isSpeaking,
    voices,
    speak,
    speakNumber,
    stop,
  }
}