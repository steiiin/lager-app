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
    } else if (event.key === 'Escape') {
      this.runCallbacks('Escape')
      this.trackBuffer = ''
      clearTimeout(this.trackTimer)
      return
    } else if (event.key === 'Backspace') {
      this.runCallbacks('Backspace')
      this.trackBuffer = ''
      clearTimeout(this.trackTimer)
      return
    }
    if (this.trackTimer) {
      clearTimeout(this.trackTimer)
    }
    this.trackTimer = setTimeout(() => {

      if (this.trackBuffer.startsWith('LC-')) {
        this.runCallbacks('scan', this.trackBuffer)
      } 
      else if (this.trackBuffer.length > 0)
      {
        if (
          (this.trackBuffer === '1' && this.runCallbacks('1')) ||
          (this.trackBuffer === '2' && this.runCallbacks('2')) ||
          (this.trackBuffer === '3' && this.runCallbacks('3')) ||
          (this.trackBuffer === 'l' && this.runCallbacks('l'))
        ) { } 
        else {
          this.runCallbacks('keys', this.trackBuffer)
        }
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
    // #region Key-l
      registerKl(callback) {
        this.registerCallback('l', callback)
      },
      unregisterKl(callback) {
        this.unregisterCallback('l', callback)
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