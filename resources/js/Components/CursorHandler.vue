<template>
  <!-- This component does not render any visible elements -->
</template>  
<script>

  import { debounce } from 'lodash'
  
  export default {
    name: 'CursorHandler',
    data() {
      return {
        cursorVisible: false,
        hideCursorTimeout: null,
        timeoutDuration: 3000,
      };
    },
    methods: {
      showCursor() {
        if (!this.cursorVisible) {
          this.cursorVisible = true;
          document.body.classList.remove('cursor-off');
          document.body.classList.add('cursor-on');
        }
        this.resetHideCursorTimer();
      },
      hideCursor() {
        this.cursorVisible = false;
        document.body.classList.remove('cursor-on');
        document.body.classList.add('cursor-off');
      },
      resetHideCursorTimer() {
        if (this.hideCursorTimeout) {
          clearTimeout(this.hideCursorTimeout);
        }
        this.hideCursorTimeout = setTimeout(this.hideCursor, this.timeoutDuration);
      },
      handleMouseMove: debounce(function () {
        this.showCursor();
      }, 100, { leading: true, trailing: false }),
      handleKeyDown() {
        this.hideCursor();
      },
    },
    mounted() {
      window.addEventListener('mousemove', this.handleMouseMove);
      window.addEventListener('keydown', this.handleKeyDown);
      this.resetHideCursorTimer();
    },
    beforeUnmount() {
      window.removeEventListener('mousemove', this.handleMouseMove);
      window.removeEventListener('keydown', this.handleKeyDown);
      if (this.hideCursorTimeout) {
        clearTimeout(this.hideCursorTimeout);
      }
    },
  };
</script>