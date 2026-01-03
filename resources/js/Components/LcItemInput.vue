<script setup>

/**
 * LcItemInput - Component
 *
 * A picker component for scanning/searching items.
 *
 * Props:
 *  - allowScan (Boolean): Enables the scan-mode.
 *  - allowNew (Boolean): Enables the new button.
 *  - hidden (Boolean): hides the input for scanning only.
 *  - disabled (Boolean): Disables the component for scanning/searching.
 *  - resultSpecs (Object): Set the width (w) and indent (i) of the absolute placed result panel.
 *  - cart (String): The current booking table, to show booking info in results.
 *
 * Emits:
 *  - createNew: Emitted when the user wants a new item.
 *  - selectItem: Emitted when the user scanned an item.
 *  - ctrlFinish: Emitted when the user scanned the finish-code.
 *  - ctrlExpired: Emitted when the user scanned the expired-code.
 *
 */

// #region Imports

  // Vue composables
  import { computed, ref, nextTick, onMounted, onUnmounted, watch } from 'vue'

  // 3rd-party composables
  import Fuse from 'fuse.js'
  import { debounce } from 'lodash'

  // Local composables
  import InputService from '@/Services/InputService'
  import { useInventoryStore } from '@/Services/StoreService'
  import { useIsPwa } from '@/Composables/useIsPwa'

  // Local components
  import LcButton from '@/Components/LcButton.vue'
  import LcScanIndicator from '@/Components/LcScanIndicator.vue'

// #endregion
// #region Props

  const { isPwa } = useIsPwa()
  const inventoryStore = useInventoryStore()

  const props = defineProps({
    allowScan: {
      type: Boolean,
      default: true,
    },
    allowNew: {
      type: Boolean,
      default: false,
    },
    hidden: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    resultSpecs: {
      type: Object,
      default: { w: 850, i: 11 },
    },
    cart: {
      type: Array,
      required: false,
      default: [],
    },
  })

  // #region TemplateProps

    const hasAnyItems = computed(() => inventoryStore.items.length > 0)

    const pickerDescriptionTitle = computed(() => {
      if (props.disabled) { return '' }
      if (hasAnyItems.value)
      {
        return isPwa.value
          ? 'Suche dein Material ...'
          : 'Scanne oder suche dein Material ...'
      }
      return 'Kein Material angelegt'
    })

    const pickerSearchBoxClasses = computed(() => {
      return 'lc-picker__search' +
        (props.allowScan ? '' : ' lc-picker__search-noscan')
    })

    const pickerResultWidth = computed(() => (props.resultSpecs?.w ?? 850) + 'px')
    const pickerResultTop = computed(() => (props.resultSpecs?.i ?? 11) + 'rem')
    const pickerResultCSS = computed(() => `height:calc(100% - 0.5rem - ${pickerResultTop.value}); top: ${pickerResultTop.value}; width: ${pickerResultWidth.value};`)

  // #endregion

// #endregion
// #region Emits

  const emit = defineEmits([
    'createNew',
    'selectItem',
    'ctrlFinish',
    'ctrlExpired',
  ])

  const selectItemBySearch = (item) => {
    emit('selectItem', item, null)
    changeModeToScan()
  }
  const selectItemByScan = (item, amount) => {
    emit('selectItem', item, amount)
  }
  const createNew = () => {
    emit('createNew')
    changeModeToScan()
  }

// #endregion

// #region Input-Logic

  // #region ItemMode

    // Props
    const currentMode = ref(props.allowScan ? 'SCAN' : 'TEXT')

    // TemplateProps
    const inScanMode = computed(() => currentMode.value === 'SCAN')
    const inTextMode = computed(() => currentMode.value === 'TEXT')

    // Methods
    const changeModeToScan = () => {
      searchText.value = ''
      if (!props.allowScan) { return }
      currentMode.value = 'SCAN'
      enableAppOverflow()
    }
    const changeModeToText = async (text = '') => {
      searchText.value = text
      currentMode.value = 'TEXT'
      await nextTick()
      document.getElementById('id-picker-searchbox')?.focus()
    }

    const disableAppOverflow = () => {
      document.getElementById('app')?.classList.add('no-overscroll')
    }
    const enableAppOverflow = () => {
      document.getElementById('app')?.classList.remove('no-overscroll')
    }

  // #endregion
  // #region ItemMode: SCAN

    const findItem = (params) => {

      const code = params.text

      // change to scanmode if code in textmode received
      if (!inScanMode.value) {
        changeModeToScan()
      }

      // emit ctrl-codes
      if (code === 'LC-2000001000') {
        emit('ctrlFinish')
        return
      }
      if (code === 'LC-2000010000') {
        emit('ctrlExpired')
        return
      }

      // search item
      const found = inventoryStore.findItemByBarcode(code)
      if (!found) { return }
      selectItemByScan(found.item, found.amount)

    }

  // #endregion
  // #region ItemMode: TEXT

    // Textbox-Props
    const searchText = ref('')
    const isTyping = ref(true)

    // Debounce Textbox
    const debouncedSearchText = ref('')
    const updateSearchText = debounce((value) => {
      debouncedSearchText.value = value
      isTyping.value = false
    }, 300)
    watch(searchText, (newValue) => {
      isTyping.value = true
      updateSearchText(newValue)
    })

    // #region Search-Logic

      // Map
      const cartMap = computed(() => {
        const map = new Map()
        props.cart.forEach(book => {
          map.set(book.item_id, book)
        })
        return map
      })

      // Props
      const hasTyped = computed(() => searchText.value.trim().length > 0)

      // Filter-Prop

      const fuseText = computed(() => {
        const items = inventoryStore.searchableItems ?? []
        return new Fuse(items, {
          includeScore: true,
          shouldSort: true,
          threshold: 0.35,            // lower = stricter;
          ignoreLocation: true,
          minMatchCharLength: 2,
          keys: [
            { name: "pp_name", weight: 0.60 },
            { name: "pp_name_alt", weight: 0.40 },
          ],
        })
      })

      const SIZE_RE = /\b(xxs|xs|s|m|l|xl|xxl|xxxl|xxxxl)\b|\b\d{1,3}(?:[.,]\d{1,2})?\b/gi

      const tokenizeText = (rawText) => {
        return (rawText ?? "")
          .toLowerCase()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .replace(/[^a-z0-9]+/g, " ")
          .split(" ")
          .filter(t => t.length >= 2)
      }

      const splitSearchText = (rawQuery) => {
        const q = (rawQuery ?? '').toLowerCase()
        const matches = q.match(SIZE_RE) ?? []
        const size = matches.map(size => size.toString().trim().toLowerCase().replace(',', '.'))
        const text = tokenizeText(q.replace(SIZE_RE, ' ').replace(/\s+/g, " ").trim())
        return { text, size }
      }

      const filteredItems = computed(() => {

        const { text, size } = splitSearchText(debouncedSearchText.value ?? '')
        if (text.length === 0 && size.length === 0) return []

        const textHits = (() => {

          // If only size typed, we still want results
          if (!text || text.length === 0) {
            return inventoryStore.searchableItems.map(item => ({
              item,
              score: 0.6,
              matchedTokens: 0,
              tokenCount: 0,
            }))
          }

          // Aggregate hits per item across tokens:
          // id -> { item, scoreSum, matchedTokens, bestScore }
          const acc = new Map()

          for (const token of text) {
            const hits = fuseText.value.search(token, { limit: 50 }) // >15 because we merge later

            for (const { item, score } of hits) {
              const prev = acc.get(item.id)

              if (!prev) {
                acc.set(item.id, {
                  item,
                  scoreSum: (score ?? 1),
                  matchedTokens: 1,
                  bestScore: (score ?? 1),
                })
              } else {
                prev.scoreSum += (score ?? 1)
                prev.matchedTokens += 1
                prev.bestScore = Math.min(prev.bestScore, (score ?? 1))
              }
            }
          }

          const tokenCount = text.length

          // Convert aggregated data to a "combined score" (lower is better)
          const combined = []
          const minCoverage = 0.8
          const requiredMatches = Math.ceil(minCoverage * tokenCount)

          for (const { item, scoreSum, matchedTokens, bestScore } of acc.values()) {
            if (matchedTokens < requiredMatches) continue

            const avgScore = scoreSum / matchedTokens
            const score = avgScore + bestScore * 0.15 // optional

            combined.push({ item, score, matchedTokens, tokenCount })
          }

          // If nothing matched any token, you can return [] (or fall back to plain search)
          return combined
        })()

        const results = []

        for (const hit of textHits) {
          const item = hit.item
          let score = hit.score ?? 1

          if (hit.tokenCount > 0 && (hit.matchedTokens ?? 0) === 0) continue

          // boost sizes match
          if (size && size.length > 0) {

            const set = item.pp_search_size
            const matchAllSizes = size.every(s => set?.has(s))
            const matchAnySize = size.some(s => set?.has(s))
            if (!matchAnySize) continue // filter non-size entries

            // Lower score = better in Fuse scoring.
            if (matchAllSizes) score *= 0.05
            else if (matchAnySize) score *= 0.2
            else score *= 2

          }

          if (score > 1) continue

          // create cart text
          const cartEntry = cartMap.value.get(item.id) || null
          const in_cart = cartEntry !== null
          const cart_description = in_cart
            ? `${cartEntry.item_amount} ${item.basesize.unit}`
            : ""

          results.push({
            ...item,
            relevance: Math.round((1 - Math.min(score ?? 1, 1)) * 100),
            in_cart,
            cart_description,
          })

        }

        results.sort((a, b) => b.relevance - a.relevance)
        if (results.length > 15) results.length = 15
        return results

      })

      // TemplateProps
      const hasAnyResults = computed(() => filteredItems.value.length > 0)
      const hasExactlyOneResult = computed(() => filteredItems.value.length === 1)
      watch(() => hasAnyResults.value, (val) => {
        if (val) {
          disableAppOverflow()
        } else {
          enableAppOverflow()
        }
      })

      // Methods
      const getFirstResult = () => (filteredItems.value[0] ?? null)
      const selectFirstResult = () => {
        if (hasExactlyOneResult.value) {
          // select only found item
          selectItemBySearch(getFirstResult())
        }
        if (searchText.value.startsWith('LC-')) {
          // scanned something while in text-mode
          searchText.value = ''
        }
        changeModeToScan()
      }

    // #endregion
    // #region Keyboard-Input

      const receiveKeys = async (params) => {

        if(props.hidden || props.disabled) { return }
        const keys = params.text

        // change to textmode if in scanmode
        if (inScanMode.value)
        {
          changeModeToText()
        }

        // focus searchbox if it is not active element
        if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
          searchText.value += keys
          currentMode.value = 'TEXT'
          await nextTick()
          document.getElementById('id-picker-searchbox')?.focus()
        }

      }
      const receiveBackspace = async () => {

        if(props.hidden || props.disabled) { return }
        if (inScanMode.value) { return }
        if (document.getElementById('id-picker-searchbox') !== document.activeElement) {
          await nextTick()
          document.getElementById('id-picker-searchbox')?.focus()
        }

      }

      const handleEscape = () => {

        if(props.hidden || props.disabled) { return false }
        if (inTextMode.value) {
          changeModeToScan()
          return false
        }
        return true

      }

      const handleEnter = () => {

        if(props.hidden || props.disabled) { return false }
        if (inTextMode.value && hasAnyItems.value) {
          if (hasExactlyOneResult.value) { selectFirstResult() }
          return false
        }
        return true

      }

    // #endregion

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {

    // init mode
    if (props.allowScan) {
      changeModeToScan()
    } else {
      changeModeToText()
    }

    // register inputs
    InputService.registerScan(findItem)
    InputService.registerKeys(receiveKeys)
    InputService.registerBackspace(receiveBackspace)

  })

  onUnmounted(() => {
    InputService.unregisterScan(findItem)
    InputService.unregisterKeys(receiveKeys)
    InputService.unregisterBackspace(receiveBackspace)
  })

// #endregion
// #region Expose

  defineExpose({ handleEscape, handleEnter })

// #endregion

</script>
<template>

  <section class="lc-picker" v-show="!hidden" v-if="inScanMode">

    <div class="lc-picker__scanner" v-if="!isPwa">
      <LcScanIndicator
        :active="hasAnyItems && !disabled && !hidden">
      </LcScanIndicator>
    </div>

    <div class="lc-picker__description">
      <div class="lc-picker__description-title">
        {{ pickerDescriptionTitle }}
      </div>
    </div>

    <template v-if="!disabled">

      <LcButton v-if="hasAnyItems && !hidden"
        class="lc-picker__action lc-picker__actionsearch" icon="mdi-form-textbox"
        @click="changeModeToText()">Suchen
      </LcButton>
      <LcButton v-if="allowNew"
        class="lc-picker__action lc-picker__actionnew" icon="mdi-plus"
        @click="createNew">Neu
      </LcButton>

    </template>

  </section>
  <section class="lc-picker" v-else-if="inTextMode">

    <LcButton v-if="allowScan"
      class="lc-picker__action" icon="mdi-arrow-left"
      @click="changeModeToScan">Zurück
    </LcButton>

    <div :class="pickerSearchBoxClasses">
      <v-text-field id="id-picker-searchbox" v-model="searchText"
        label="Suche nach Material ..." variant="outlined" hide-details :rounded="0"
        @keyup.enter="selectFirstResult">
      </v-text-field>
    </div>

  </section>
  <section class="lc-picker__result" v-if="hasAnyItems && hasTyped" :style="pickerResultCSS">
    <div class="lc-picker__result--fade-top"></div>

    <v-empty-state v-show="!hasAnyResults && !isTyping"
      :title="isTyping ? '' : 'Kein Material gefunden'"
      :text="isTyping ? '' : 'Versuche einen anderen Suchbegriff'"
    ></v-empty-state>

    <v-card v-for="item in filteredItems" :key="item.id"
      class="lc-picker__result--item" link variant="flat"
      @click="selectItemBySearch(item)">
      <div class="lc-picker__result--item-head">
        <div class="lc-picker__result--item-head-name">{{ item.name }}</div>
        <v-spacer></v-spacer>
        <div class="lc-picker__result--item-head-demand">{{ item.demand?.name }}</div>
      </div>
      <template v-if="item.has_altnames">
        <div class="lc-picker__result--item-tags">
          <v-chip size="small" label variant="outlined" v-for="tag in item.pp_name_alt">{{ tag }}</v-chip>
        </div>
      </template>
      <v-divider class="my-2"></v-divider>
      <div class="lc-picker__result--item-location">
        <v-chip prepend-icon="mdi-domain" variant="text">{{ item.location.room }}</v-chip>
        <template v-if="!!item.location.cab">
          <v-chip prepend-icon="mdi-fridge" variant="text">{{ item.location.cab }}</v-chip>
        </template>
        <div v-if="!!item.location.exact">
          <v-chip prepend-icon="mdi-archive-marker-outline" variant="text">{{ item.location.exact }}</v-chip>
        </div>
      </div>
      <template v-if="item.in_cart">
        <v-divider class="my-2"></v-divider>
        <div class="d-flex flex-row-reverse">
          <v-chip color="black" label prepend-icon="mdi-check-circle" variant="flat">Schon im Warenkorb:&nbsp;<b>{{ item.bookingText }}</b></v-chip>
        </div>
      </template>
    </v-card>

    <template v-if="hasExactlyOneResult">
      <div class="lc-picker__result--enterhint" >
        Drücke <kbd sym>&crarr;</kbd> um <b>&nbsp;{{ getFirstResult().name }}&nbsp;</b> auszuwählen.
      </div>
    </template>

    <div class="lc-picker__result--overlay" v-if="isTyping">
      <div class="lc-picker__result--overlay-text">Suche ...</div>
    </div>

  </section>

</template>
<style lang="scss" scoped>
.lc-picker {

  display: flex;
  gap: .5rem;

  &__scanner,
  &__action {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 8rem;
    height: 8rem;
  }
  &__scanner {
    background: var(--accent-secondary-background);
  }
  &__camscanner {
    grid-area: CamScanner;
  }

  &__description {
    flex: 1;
    border: .5rem solid var(--accent-secondary-background);
    background: var(--accent-secondary-background);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: end;
    grid-area: Description;

    & > * {
      opacity: .3;
    }
    &-title {
      font-weight: bold;
      font-size: 1.3rem;
    }
  }

  &__actionsearch {
    grid-area: ButtonSearch;
  }
  &__actionnew {
    grid-area: ButtonNew;
  }

  &__search {
    flex: 1;
    border: .5rem solid var(--accent-secondary-background);
    padding: 1.7rem;
    &-noscan {
      padding: 0;
    }
  }

  &__result {

    position: absolute;
    top: 16.5rem;
    width: 850px;
    height: 100%;
    margin-top: .5rem;
    padding: .5rem 0 0 0;

    background: var(--main-light);

    overflow-y: auto;
    overflow-x: hidden;
    z-index: 999;

    &--item {

      background: var(--accent-secondary-background);
      padding: .5rem;
      margin-bottom: .5rem;
      border-radius: 0;

      &-head {

        display: flex;
        justify-content: space-between;
        gap: .5rem;

        &-name {
          font-weight: bold;
          font-size: 1.2rem;
        }
        &-demand {
          background: var(--accent-primary-background);
          color: var(--accent-primary-foreground);
          padding: 2px 1rem;
        }

      }
      &-tags {
        display: flex;
        gap: 4px;
        align-items: center;
        margin-top: 4px;
      }

    }

    &--enterhint {
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 0.8rem;
      opacity: 0.8;
    }

    &--overlay {
      background-color: var(--overlay-translucent-background);
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      &-text {
        background-color: var(--accent-secondary-background);
        color: var(--accent-secondary-foreground);
        padding: .5rem 1.5rem;
      }
    }

    &--fade-top {
      position: fixed;
      height: .5rem;
      width: 100%;
      margin-top: -0.5rem;
      z-index: 10;
      background: linear-gradient(to top, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 1) 75%);
    }

  }

}

.lc-picker.isPwa {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  grid-template-rows: 1fr auto;
  grid-template-areas:
    "CamScanner Description ButtonSearch"
    "CamScanner Description ButtonNew";
}

</style>
