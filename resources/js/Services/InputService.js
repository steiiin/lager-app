// #region state

  import { reactive } from 'vue'
  const state = reactive({
    handlers: {
    },
  })

// #endregion

const InputService = {

  binding: null,
  trackBuffer: '',
  trackTimer: null,

  trackInput (event) {
    if (event.key.length === 1) {
      this.trackBuffer += event.key
    } else if (event.key === 'Enter') {
      if (this.trackBuffer.startsWith('LC-')) {
        this.runCallbacks('scan', this.trackBuffer)
      } else {
        this.runCallbacks('Enter')
      }
      this.trackBuffer = ''
      clearTimeout(this.trackTimer)
      return
    } else {
      if (state.handlers.hasOwnProperty(event.key)) {
        this.runCallbacks(event.key)
        this.trackBuffer = ''
        clearTimeout(this.trackTimer)
        return
      }
    }
    
    if (this.trackTimer) {
      clearTimeout(this.trackTimer)
    }
    this.trackTimer = setTimeout(() => {

      if (this.trackBuffer.startsWith('LC-')) {
        this.runCallbacks('scan', this.trackBuffer)
      } 
      else if (this.trackBuffer.length === 1) 
      {
        if (state.handlers.hasOwnProperty(this.trackBuffer)) {
          this.runCallbacks(this.trackBuffer)
        } else {
          this.runCallbacks('keys', this.trackBuffer)
        }
      }
      else if (this.trackBuffer.length > 0)
      {
        this.runCallbacks('keys', this.trackBuffer)
      }
      this.trackBuffer = ''
    }, 50)
  },

  registerCallback(key, callback) {
    if (!state.handlers[key]) { state.handlers[key] = [] }
    state.handlers[key].push(callback)
  },
  unregisterCallback(key, callback) {
    if(!state.handlers[key]) { return }
    state.handlers[key] = state.handlers[key].filter(
      (handler) => handler !== callback
    )
    if (state.handlers[key].length === 0) { delete state.handlers[key] }
  },
  runCallbacks(key, props = undefined) {
    let runSome = false
    if (state.handlers[key]) {
      state.handlers[key].forEach((handler) => {
        handler(props)
        runSome = true
      })
    }
    return runSome
  },

  // #region Callbacks

    // #region Key-1
      registerK1(callback) {
        this.registerCallback('1', callback)
      },
      unregisterK1(callback) {
        this.unregisterCallback('1', callback)
      },
    // #endregion
    // #region Key-2
      registerK2(callback) {
        this.registerCallback('2', callback)
      },
      unregisterK2(callback) {
        this.unregisterCallback('2', callback)
      },
    // #endregion
    // #region Key-3
      registerK3(callback) {
        this.registerCallback('3', callback)
      },
      unregisterK3(callback) {
        this.unregisterCallback('3', callback)
      },
    // #endregion
    // #region Key-KioskSettings
      registerKKiosk(callback) {
        this.registerCallback('y', callback)
      },
      unregisterKKiosk(callback) {
        this.unregisterCallback('y', callback)
      },
    // #endregion
    // #region Input
      registerScan(callback) {
        this.registerCallback('scan', callback)
      },
      unregisterScan(callback) {
        this.unregisterCallback('scan', callback)
      },
      registerKeys(callback) {
        this.registerCallback('keys', callback)
      },
      unregisterKeys(callback) {
        this.unregisterCallback('keys', callback)
      },
    // #endregion
    // #region Esc
      registerEsc(callback) {
        this.registerCallback('Escape', callback)
      },
      unregisterEsc(callback) {
        this.unregisterCallback('Escape', callback)
      },
    // #endregion
    // #region Backspace
      registerBackspace(callback) {
        this.registerCallback('Backspace', callback)
      },
      unregisterBackspace(callback) {
        this.unregisterCallback('Backspace', callback)
      },
    // #endregion
    // #region Enter
      registerEnter(callback) {
        this.registerCallback('Enter', callback)
      },
      unregisterEnter(callback) {
        this.unregisterCallback('Enter', callback)
      },
    // #endregion
    // #region Arrows
      registerUp(callback) {
        this.registerCallback('ArrowUp', callback)
      },
      unregisterUp(callback) {
        this.unregisterCallback('ArrowUp', callback)
      },
      registerDown(callback) {
        this.registerCallback('ArrowDown', callback)
      },
      unregisterDown(callback) {
        this.unregisterCallback('ArrowDown', callback)
      },
      registerLeft(callback) {
        this.registerCallback('ArrowLeft', callback)
      },
      unregisterLeft(callback) {
        this.unregisterCallback('ArrowLeft', callback)
      },
      registerRight(callback) {
        this.registerCallback('ArrowRight', callback)
      },
      unregisterRight(callback) {
        this.unregisterCallback('ArrowRight', callback)
      },
  // #endregion

  // #endregion

  initialize() {
    this.binding = this.trackInput.bind(this)
    window.addEventListener('keydown', this.binding)
  },
  destroy() {
    window.removeEventListener('keydown', this.binding)
  },

}

export default InputService