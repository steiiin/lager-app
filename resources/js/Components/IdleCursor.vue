<script setup>

/**
 * IdleCursor - Component
 *
 * This component handles cursor visibility based on user activity.
 * It hides the cursor after 3000ms of inactivity, and shows it
 * again when the user moves the mouse.
 * It also hides the cursor when any key is pressed.
 *
 */

// #region Imports

  import { onMounted, onUnmounted } from 'vue'
  import { debounce } from 'lodash'

// #endregion
// #region Lifecycle

  onMounted(() => {
    window.addEventListener('mousemove', handleMouseMove)
    window.addEventListener('keydown', handleKeyDown)
    resetHideCursorTimer()
  })
  onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove)
    window.removeEventListener('keydown', handleKeyDown)
    clearTimeout(hideCursorTimeout)
  })

// #endregion

// #region CursorLogic

  let cursorVisible = false
  let hideCursorTimeout = null
  const timeoutDuration = 3000

  const showCursor = () => {
    if (!cursorVisible) {
      cursorVisible = true
      document.body.classList.remove('cursor-off')
    }
    resetHideCursorTimer()
  }
  const hideCursor = () => {
    cursorVisible = false
    document.body.classList.add('cursor-off')
  }

// #endregion
// #region IdleLogic

    const resetHideCursorTimer = () => {
      clearTimeout(hideCursorTimeout)
      hideCursorTimeout = setTimeout(hideCursor, timeoutDuration)
    }

    const handleMouseMove = debounce(() => {
      showCursor()
    }, 100, { leading: true, trailing: false })
    const handleKeyDown = () => {
      hideCursor();
    }

  // #endregion

</script>
<template></template>