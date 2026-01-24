<script setup>

/**
 * Inventory - Page component
 *
 * This page enables editing items.
 * Additionally it show info for easier management.
 *
 */

// #region Imports

  // Vue composables
  import { ref, computed, nextTick, watch, toRef, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { useBaseSize } from '@/Composables/useBaseSize'
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'

  import { useIsPwa } from '@/Composables/useIsPwa'
  const { isPwa } = useIsPwa()

  import { useSpeech } from '@/Composables/useSpeech'
  const { speakNumber, isSpeaking, stop } = useSpeech()

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcButtonGroup from '@/Components/LcButtonGroup.vue'
  import LcItemSizeDialog from '@/Dialogs/LcItemSizeDialog.vue'

  import LcCheckTags from '@/Components/Inventory/LcCheckTags.vue'
  import LcStockAmount from '@/Components/Inventory/LcStockAmount.vue'
  import LcTrend from '@/Components/Inventory/LcTrend.vue'

  // 3rd party components
  import axios from 'axios'
import { reactive } from 'vue'

// #endregion
// #region Props

  const inventoryStore = useInventoryStore()

  const props = defineProps({
    demands: {
      type: Array,
      required: true,
    },
  })

// #endregion
// #region Navigation

  // Router-Events
  const isRouting = ref(false)
  router.on('start', () => isRouting.value = true)
  router.on('finish', () => isRouting.value = false)

  // Routes
  function openWelcome() {
    router.get('/')
  }
  function openInventoryDemands() {
    router.get('/inventory-demands')
  }
  function openInventoryUsages() {
    router.get('/inventory-usages')
  }
  function openInventoryLabels() {
    router.get('/inventory-labels')
  }
  function openInventoryInsights() {
    router.get('/inventory-insights')
  }

  // #region KeyboardShortcuts

    const handleIdle = () => {
      openWelcome()
    }

    const handleEsc = () => {
      if (isItemSelected.value) {
        clearSelectedItem()
      } else {
        openWelcome()
      }
    }
    const handleEnter = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        saveItem()
      }
    }

    const handleLeft = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        itemForm.current_quantity -= 1
      }
    }
    const handleRight = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value) {
        itemForm.current_quantity += 1
      }
    }

  // #endregion

// #endregion

// #region Inventory-Logic

  // #region Modes

    const inventoryMode = ref('edit')
    const inEditMode = computed(() => inventoryMode.value == 'edit')
    const inCheckMode = computed(() => inventoryMode.value == 'check')

    const toEditMode = () => {
      inventoryMode.value = 'edit'
      editorAccordionOpened.value = []
    }

    const toCheckMode = () => {
      inventoryMode.value = 'check'
      editorAccordionOpened.value = [ 4, 5 ]
    }

  // #endregion

  // #region Dashboard

    // Methods
    const findCheckTags = (item) => {

      // check      (if check is necessary)   'Noch Nie'|'12.12.2025'
      // expiry     (if expiry is near)       'Kein MHD'|'12-2025'
      // onvehicle  (if onvehicle>max_stock)

      const normalTimespan = (new Date()); normalTimespan.setDate(normalTimespan.getDate() - 60); normalTimespan.setHours(0, 0, 0, 0);
      const strictTimespan = (new Date()); strictTimespan.setDate(strictTimespan.getDate() - 25); strictTimespan.setHours(0, 0, 0, 0);

      const tags = []

      if (!item.checked_at) { tags.push({ type: 'check', label: 'Noch Nie' }) }
      else {

        const checked_at = new Date(item.checked_at)
        const belowNormal = !isNaN(checked_at) && checked_at <= normalTimespan
        const belowStrict = !isNaN(checked_at) && checked_at <= strictTimespan

        if (belowNormal) { tags.push({ type: 'check', label: getLastCheckedLabel(item.checked_at) }) }
        if (belowStrict && (item.max_stock <= item.onvehicle_stock)) {
          tags.push({ type: 'check', label: getLastCheckedLabel(item.checked_at) })
          tags.push({ type: 'onvehicle' })
        }

      }

      if (item.ordered_in_last_order && item.last_order_date) {
        const lastOrderDate = new Date(item.last_order_date)
        const lastCheckDate = item.checked_at ? new Date(item.checked_at) : null
        const hasValidCheckDate = lastCheckDate && !isNaN(lastCheckDate)
        const orderAfterCheck = !hasValidCheckDate || (!isNaN(lastOrderDate) && lastOrderDate > lastCheckDate)

        if (orderAfterCheck) {
          tags.push({ type: 'check', label: `Bestellt: ${getLastCheckedLabel(item.last_order_date)}` })
        }
      }

      if (!item.current_expiry) {  }
      else {

        const thresholdExpiry = (new Date()); thresholdExpiry.setDate(thresholdExpiry.getDate() + 21); thresholdExpiry.setHours(0, 0, 0, 0);
        const current_expiry = new Date(item.current_expiry)
        if (current_expiry <= thresholdExpiry) {
          tags.push({ type: 'expiry', label: getExpiryLabel(item.current_expiry) })
        }

      }

      return tags

    }

    // FilterProps
    const itemsCheckNecessary = computed(() => {

      return inventoryStore.items
        .map(item => { return { ...item, tags: findCheckTags(item)} })
        .filter(item => item.tags.length>0)
        .sort((a, b) => {

            if (!a.checked_at && !b.checked_at) {
            } else if (!a.checked_at) {
              return -1; // a (null) comes before b
            } else if (!b.checked_at) {
              return 1;  // b (null) comes before a
            } else {
              const dateA = new Date(a.checked_at);
              const dateB = new Date(b.checked_at);
              if (dateA < dateB) return -1;
              if (dateA > dateB) return 1;
            }

            if (a.current_expiry && !b.current_expiry) {
              return -1; // a has expiry → comes first
            }
            if (!a.current_expiry && b.current_expiry) {
              return 1;  // b has expiry → comes first
            }
            if (!a.current_expiry && !b.current_expiry) {
              return 0;  // both null → equal
            }

            const expiryA = new Date(a.current_expiry);
            const expiryB = new Date(b.current_expiry);
            return expiryA - expiryB; // shorter syntax for ascending
          });

    })

    // Table
    const tableCheckNecessary = ref([
      { title: 'Name', key: 'name' },
      { title: '', key: 'tags', align: 'end' },
      { title: '', key: 'action', sortable: false },
    ])

  // #endregion

  // #region ItemPicker

    const selectedItem = ref(null)
    const isItemSelected = computed(() => !!selectedItem.value)

    const clearSelectedItem = async (itemChanged = false) => {

      if (itemChanged) {
        const nextItem = nextItemToCheck()
        if (!!nextItem) { editItem(nextItem); return }
      } else {
        checkAllList.value = []
      }

      selectedItem.value = null

    }

    // #############################################

    const checkAllList = ref([])

    const checkAllNecessaryItems = () => {

      const collator = new Intl.Collator('de', {
        sensitivity: 'base',
        numeric: true,
      })
      const norm = v => v ?? ''

      checkAllList.value = itemsCheckNecessary.value.sort((a, b) => {
        const la = a.location ?? {}
        const lb = b.location ?? {}

        return (
          collator.compare(norm(la.room),  norm(lb.room))  ||
          collator.compare(norm(la.cab),   norm(lb.cab))   ||
          collator.compare(norm(la.exact), norm(lb.exact))
        )
      }).map(item => item.id)

      clearSelectedItem(true)
    }

    const nextItemToCheck = () => {
      if (checkAllList.value.length>0) {
        const nextId = checkAllList.value.shift()
        const nextItem = inventoryStore.items.find(item => item.id === nextId)
        if (!!nextItem) { return nextItem}
      }
      return null
    }

  // #endregion
  // #region ItemEditor

    const editorTitle = computed(() => {
      if (isNewItem.value) { return 'Neuer Artikel' }
      if (inCheckMode.value) { return `Prüfen: ${itemForm.name}` }
      return `Bearbeiten: ${itemForm.name}`
    })

    const saveBtnLabel = computed(() => {
      if (inCheckMode.value) { return 'Geprüft' }
      return 'Speichern'
    })

    // Form
    const itemForm = useForm({

      id: null,
      name: '',
      name_alt: '',
      search_size: '',
      demand_id: null,
      location: { room:'', cab:'', exact:'' },
      min_stock: 0,
      max_stock: 0,
      current_expiry: null,
      current_quantity: 0,
      onvehicle_stock: 0,
      checked_at: null,
      max_order_quantity: 0,
      max_bookin_quantity: 0,

      sizes: [],

      stockchangeReason: -1,

    })

    const itemFormOptions = {
      preserveScroll: true,
      onSuccess: () => {

        clearSelectedItem(true)

        router.reload()
        inventoryStore.fetchStore(true)

      },
    }

    // OpenMethods
    const createNew = () => {

      inventoryMode.value = 'edit'
      itemForm.reset()
      checkAllList.value = []

      itemForm.id = null
      itemForm.name = 'Neuer Artikel'
      itemForm.name_alt = ''
      itemForm.search_size = ''
      itemForm.demand_id = props.demands[0]?.id
      itemForm.location = {
        room: 'Lagerraum',
        cab: '',
        exact: '',
      }
      itemForm.min_stock = 0
      itemForm.max_stock = 0
      itemForm.onvehicle_stock = 1

      itemForm.current_expiry = null
      currentExpiryMonth.value = new Date().getMonth() + 1
      currentExpiryYear.value = (new Date()).getFullYear()
      currentNoExpiry.value = true
      updateExpiry()

      itemForm.current_quantity = 0
      itemForm.checked_at = null
      itemForm.max_order_quantity = 0
      itemForm.max_bookin_quantity = 0

      itemForm.sizes.splice(0, itemForm.sizes.length)
      itemForm.sizes.push({ id: null, unit: 'Stk.', amount: 1, is_default: true })

      editorAccordionOpened.value = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ]
      selectedItem.value = { new: true }

    }
    const editItem = (item) => {

      itemForm.reset()

      itemForm.id = item.id
      itemForm.name = item.name
      itemForm.name_alt = item.name_alt
      itemForm.search_size = item.search_size
      itemForm.demand_id = item.demand_id
      itemForm.location = {
        room: item.location.room,
        cab: item.location.cab,
        exact: item.location.exact,
      }
      itemForm.min_stock = item.min_stock
      itemForm.max_stock = item.max_stock
      itemForm.onvehicle_stock = item.onvehicle_stock

      itemForm.current_expiry = !item.current_expiry ? null : new Date(item.current_expiry)
      currentNoExpiry.value = !item.current_expiry
      currentExpiryMonth.value = !itemForm.current_expiry ? null : itemForm.current_expiry.getMonth()+1
      currentExpiryYear.value = itemForm.current_expiry?.getFullYear() ?? null

      itemForm.current_quantity = item.current_quantity
      itemForm.checked_at = !item.checked_at ? null : new Date(item.checked_at)
      itemForm.max_order_quantity = item.max_order_quantity
      itemForm.max_bookin_quantity = item.max_bookin_quantity

      itemForm.sizes = item.sizes
      itemForm.stockchangeReason = -1

      editorAccordionOpened.value = inCheckMode.value ? [ 4, 5 ] : []
      selectedItem.value = { edit: true }

      itemStats.has_stats = item.has_stats
      itemStats.weeks_total = item.weeks_total
      itemStats.weeks_recent = item.weeks_recent
      itemStats.consumption_total_sum = item.consumption_total_sum
      itemStats.consumption_recent_sum = item.consumption_recent_sum
      itemStats.adjustment_total_sum = item.adjustment_total_sum
      itemStats.adjustment_recent_sum = item.adjustment_recent_sum
      itemStats.consumption_week_max_total = item.consumption_week_max_total
      itemStats.consumption_week_max_recent = item.consumption_week_max_recent
      itemStats.booking_max_total = item.booking_max_total
      itemStats.consumption_week_stddev_total = item.consumption_week_stddev_total

      itemStats.consumption_per_week_total = item.consumption_per_week_total
      itemStats.consumption_per_week_recent = item.consumption_per_week_recent
      itemStats.consumption_trend = item.consumption_trend
      itemStats.adjustment_per_week_total = item.adjustment_per_week_total
      itemStats.adjustment_per_week_recent = item.adjustment_per_week_recent
      itemStats.adjustment_trend = item.adjustment_trend
      itemStats.is_problem_item = item.is_problem_item

      loadSpeechIfNecessary()

    }
    const changeItem = (item) => {
      clearSelectedItem()
      editItem(item)
    }

    // ActionMethods
    const saveItem = async () => {

      if (isNewItem.value) {
        itemForm.post('/inventory', itemFormOptions)
      } else {
        itemForm.put(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }
    const deleteItem = () => {

      if (confirm('Willst du das wirklich löschen?')) {
        itemForm.delete(`/inventory/${itemForm.id}`, itemFormOptions)
      }

    }

    // #region TemplateProps

      const isNewItem = computed(() => itemForm.id === null)
      const editorAccordionOpened = ref([])

      // Validation
      const isValidName = computed(() => itemForm.name.trim().length>0 )
      const isValidDemand = computed(() => !!itemForm.demand_id )
      const isValidItem = computed(() => isValidName.value && isValidDemand.value )

      // Check
      const currentLastCheckedLabel = computed(() => getLastCheckedLabel(itemForm.checked_at))

      const getLastCheckedLabel = (dateString) => {
        if (!dateString) { return 'Nie geprüft' }
        const date = new Date(dateString)
        const day = date.getDate()
        const month = date.getMonth()+1
        const year = date.getFullYear()
        return `${day}.${month}.${year}`
      }

      const getExpiryLabel = (dateString) => {
        if (!dateString) { return 'Kein MHD' }
        const date = new Date(dateString)
        const month = (date.getMonth()+1).toString().padStart(2, '0')
        const year = date.getFullYear()
        return `${month}-${year}`
      }

      const isSizeDialogVisible = ref(false)
      const isStockDialogVisible = ref(false)

    // #endregion

    // #region Sizes-Group

      const { baseUnit } = useBaseSize(toRef(itemForm, 'sizes'))

      const sizeDialog = ref(null)
      const createSize = () => sizeDialog.value?.create()
      const editSize = (item) => sizeDialog.value?.edit(item)

      const sizesHeaders = ref([
        { title: 'Einheit', key: 'unit', minWidth: '30%' },
        { title: 'Menge', key: 'amount', minWidth: '20%' },
        { title: 'Bearbeiten', key: 'action', sortable: false },
      ])
      const sizesSortBy = ref([
        { key: 'amount', order: 'asc' }
      ])

    // #endregion
    // #region Quantity/Expiry-Group

      const currentExpiryMonth = ref(null)
      const currentExpiryYear = ref(null)
      const currentNoExpiry = ref(true)
      const selectableStockChangeReasons = ref([
        { name: "Abweichung", value: -1 },
        { name: "Rückbuchung", value: -2 },
        { name: "Verfall", value: -3 },
      ])

      const isValidExpiry = computed(() => currentNoExpiry.value || (itemForm.current_expiry !== null && !isNaN(itemForm.current_expiry)))

      // Expiry-Props
      const updateExpiry = () => {
        if (currentNoExpiry.value) {
          itemForm.current_expiry = null
        } else if (!currentExpiryMonth.value || !currentExpiryYear.value) {
          itemForm.current_expiry = null
        } else {
          itemForm.current_expiry = new Date(currentExpiryYear.value, currentExpiryMonth.value, 0)
        }
      }
      watch(currentExpiryMonth, () => {
        updateExpiry()
      })
      watch(currentExpiryYear, () => {
        updateExpiry()
      })
      watch(currentNoExpiry, () => {
        updateExpiry()
      })

      const loadSpeechIfNecessary = () => {
        if (inCheckMode.value && !isNewItem.value) {
          setTimeout(() => speakNumber(itemForm.current_quantity), 500)
        }
      }

    // #endregion
    // #region Stats-Group

      const itemStats = reactive({
        has_stats: false,
        weeks_total: 0,
        weeks_recent: 0,
        consumption_total_sum: 0,
        consumption_recent_sum: 0,
        adjustment_total_sum: 0,
        adjustment_recent_sum: 0,
        consumption_week_max_total: 0,
        consumption_week_max_recent: 0,
        booking_max_total: 0,
        consumption_week_stddev_total: 0,
        consumption_per_week_total: 0,
        consumption_per_week_recent: 0,
        consumption_trend: 0,
        adjustment_per_week_total: 0,
        adjustment_per_week_recent: 0,
        adjustment_trend: 0,
        is_problem_item: false,
      })

    // #endregion

  // #endregion

// #endregion

// #region Lifecycle

  onMounted(() => {
    InputService.registerLeft(handleLeft)
    InputService.registerRight(handleRight)
    InputService.registerEsc(handleEsc)
    InputService.registerEnter(handleEnter)
    InputService.registerIdle(handleIdle)
    inventoryStore.fetchStore(true)
    document.body.classList.remove('cursor-off')
  })
  onUnmounted(() => {
    InputService.unregisterLeft(handleLeft)
    InputService.unregisterRight(handleRight)
    InputService.unregisterEsc(handleEsc)
    InputService.unregisterEnter(handleEnter)
    InputService.unregisterIdle(handleIdle)
  })

// #endregion

</script>

<template>

  <Head title="Inventar" />

  <div class="page-inventory">

    <LcPagebar :title="isPwa ? 'Inventar-App' : 'Inventar'" @back="openWelcome" :disabled="isPwa || isItemSelected">
      <template #actions>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openInventoryDemands">Anforderungen
        </v-btn>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openInventoryUsages">Verwendungen
        </v-btn>
        <v-btn v-if="!isItemSelected" variant="flat"
          @click="openInventoryLabels">Labels
        </v-btn>
      </template>
    </LcPagebar>

    <section>

      <template v-if="!isItemSelected">

        <LcButtonGroup v-model="inventoryMode" :items="[
          { label: 'Bearbeiten', value: 'edit' },
          { label: 'Prüfen', value: 'check' },
        ]"></LcButtonGroup>

        <LcItemInput
          :result-specs="{ w: 850, i: 19.0 }" :allow-new="inEditMode" :disabled="isItemSelected"
          @create-new="createNew" @select-item="editItem">
        </LcItemInput>

        <!-- Dashboard -->
        <v-card class="mt-2" variant="outlined" v-show="inCheckMode">
          <v-list-item class="px-6" height="88">
            <template v-slot:prepend>
              <v-icon icon="mdi-gauge-low"></v-icon>
            </template>
            <template v-slot:title> <b>Entnahme & Insights</b> </template>

            <template v-slot:append>
              <v-btn v-if="inCheckMode"
                class="text-none"
                color="primary"
                text="DASHBOARD ÖFFNEN"
                variant="text"
                slim @click="openInventoryInsights"
              ></v-btn>
            </template>
          </v-list-item>
        </v-card>

        <!-- CheckBoad -->
        <v-card class="mt-2" variant="outlined" v-show="inCheckMode">
          <v-list-item class="px-6" height="88">
            <template v-slot:prepend>
              <v-icon icon="mdi-clipboard-clock"></v-icon>
            </template>

            <template v-slot:title> <b>Regelmäßige Prüfung</b> </template>

            <template v-slot:append>
              <v-btn v-if="inCheckMode"
                class="text-none"
                color="primary"
                text="PRÜFE ALLE"
                variant="text"
                slim @click="checkAllNecessaryItems"
              ></v-btn>
            </template>
          </v-list-item>

          <v-divider></v-divider>
          <v-card-text>

            <v-data-table
              :items="itemsCheckNecessary"
              :headers="tableCheckNecessary"
              :items-per-page="20">
              <template v-slot:item.tags="{ item }">
                <LcCheckTags :tags="item.tags"></LcCheckTags>
              </template>
              <template v-slot:item.action="{ item }">
                <v-btn small variant="outlined" @click="editItem(item)">
                  <v-icon icon="mdi-cog"></v-icon>
                </v-btn>
              </template>
            </v-data-table>

          </v-card-text>
        </v-card>

      </template>
      <template v-else>

        <LcItemInput @select-item="changeItem" hidden></LcItemInput>

        <v-toolbar flat>
          <v-app-bar-nav-icon
            icon="mdi-arrow-left" :disabled="itemForm.processing"
            @click="clearSelectedItem()"></v-app-bar-nav-icon>
          <v-toolbar-title>
            <b>{{ editorTitle }}</b>
          </v-toolbar-title>
          <v-toolbar-items>
            <v-btn v-if="inCheckMode"
              @click="toEditMode"
              icon="mdi-wrench"
              v-tooltip:bottom="'Artikel bearbeiten'"
            ></v-btn>
            <v-btn v-if="inEditMode && !isNewItem"
              @click="toCheckMode"
              icon="mdi-check"
              v-tooltip:bottom="'Nur Prüfen'"
            ></v-btn>
          </v-toolbar-items>
        </v-toolbar>

        <v-expansion-panels class="mt-2" flat multiple
          v-model="editorAccordionOpened" :disabled="itemForm.processing">

          <v-expansion-panel class="mt-1" title="Allgemein" color="black" outlined v-show="inEditMode">
            <v-expansion-panel-text>

              <v-text-field v-model="itemForm.name"
                label="Name" required hide-details>
              </v-text-field>
              <v-alert v-if="!isValidName"
                text="Du musst einen Namen angeben."
                type="error">
              </v-alert>

              <v-text-field v-model="itemForm.name_alt"
                class="mt-2" label="Alternative Namen" hide-details>
              </v-text-field>
              <v-text-field v-model="itemForm.search_size"
                class="mt-2" label="Größenangabe ('m' oder 'm,6.5')" hide-details>
              </v-text-field>

              <v-select v-model="itemForm.demand_id" :items="demands"
                class="mt-2" label="Anforderung" item-title="name"
                item-value="id" required hide-details>
              </v-select>
              <v-alert v-if="!isValidDemand"
                text="Du musst eine Anforderung angeben."
                type="error">
              </v-alert>

            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Wo ist ... ?" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-text-field v-model="itemForm.location.room"
                label="Raum" hide-details>
              </v-text-field>

              <v-text-field v-model="itemForm.location.cab"
                label="Schrank" hide-details>
              </v-text-field>

              <v-text-field v-model="itemForm.location.exact"
                label="Genauer Ort (z.B. Schublade)" hide-details>
              </v-text-field>

            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Packungsgrößen" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-data-table :items="itemForm.sizes"
                :headers="sizesHeaders" :sort-by="sizesSortBy"
                hide-default-footer :items-per-page="100">
                <template v-slot:item.action="{ item }">
                  <v-btn small variant="outlined" @click="editSize(item)">
                    <v-icon icon="mdi-cog"></v-icon>
                  </v-btn>
                </template>
              </v-data-table>

              <v-divider></v-divider>

              <v-btn color="primary" variant="outlined" class="mt-4" prepend-icon="mdi-plus"
                @click="createSize()">Hinzufügen
              </v-btn>

              </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Min- & Maxbestand" color="black" v-show="inEditMode">
            <v-expansion-panel-text>

              <v-form class="mb-6">

                <LcStockAmount
                  v-model:stock="itemForm.min_stock"
                  v-model:visible="isStockDialogVisible"
                  :sizes="itemForm.sizes"
                  title="Min-Bestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Min-Bestand zu errechnen."
                  button-text="Min-Bestand ändern">
                </LcStockAmount>

                <LcStockAmount
                  v-model:stock="itemForm.max_stock"
                  v-model:visible="isStockDialogVisible"
                  :sizes="itemForm.sizes"
                  title="Max-Bestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Max-Bestand zu errechnen."
                  button-text="Max-Bestand ändern">
                </LcStockAmount>

              </v-form>
              <v-form class="mb-6">

                <LcStockAmount
                  v-model:stock="itemForm.onvehicle_stock"
                  v-model:visible="isStockDialogVisible"
                  :sizes="itemForm.sizes"
                  title="Fahrzeugbestand berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um einen neuen Fahrzeugbestand zu errechnen."
                  button-text="Fahrzeugbestand ändern">
                </LcStockAmount>

              </v-form>
              <v-form class="pb-3">

                <LcStockAmount
                  v-model:stock="itemForm.max_bookin_quantity"
                  v-model:visible="isStockDialogVisible"
                  :sizes="itemForm.sizes"
                  title="Max/Buchung berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um die maximal Menge pro Buchung einzugeben."
                  button-text="Max/Buchung ändern">
                </LcStockAmount>

                <LcStockAmount
                  v-model:stock="itemForm.max_order_quantity"
                  v-model:visible="isStockDialogVisible"
                  :sizes="itemForm.sizes"
                  title="Max/Bestellung berechnen"
                  message="Gib eine Packungsgröße und eine Menge ein, um die maximal Menge pro Bestellung einzugeben."
                  button-text="Max/Bestellung ändern">
                </LcStockAmount>

              </v-form>


            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Aktueller Bestand" color="black">
            <v-expansion-panel-text>

              <v-form>
                <v-row>
                  <v-col cols="4" class="page-inventory__table--result">
                    Aktueller Bestand ({{ baseUnit }})
                  </v-col>
                  <v-col cols="5">
                    <v-number-input
                      v-model="itemForm.current_quantity"
                      :reverse="false"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="-999"
                      :max="999"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="3">
                    <v-select v-if="!isNewItem"
                      v-model="itemForm.stockchangeReason"
                      :items="selectableStockChangeReasons"
                      label="Grund"
                      item-title="name"
                      item-value="value"
                      required
                      hide-details
                    ></v-select>
                  </v-col>
                </v-row>
                <v-row class="mt-2">
                  <v-col cols="4" class="page-inventory__table--result">
                    Nächster Verfall
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryMonth"
                      :reverse="false" :disabled="currentNoExpiry"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="1"
                      :max="12"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="3" class="align-content-center">
                    <v-number-input
                      v-model="currentExpiryYear"
                      :reverse="false" :disabled="currentNoExpiry"
                      controlVariant="split"
                      :hideInput="false"
                      :inset="false" hide-details
                      :min="(new Date()).getFullYear() - 1"
                      :max="(new Date()).getFullYear() + 99"
                    ></v-number-input>
                  </v-col>
                  <v-col cols="2">
                    <v-checkbox v-model="currentNoExpiry"
                      label="Ohne MHD" hide-details>
                    </v-checkbox>
                  </v-col>
                </v-row>
                <v-row class="mt-n5">
                  <v-col cols="4"></v-col>
                  <v-col cols="5">
                    <v-alert v-if="!isValidExpiry"
                      text="Gib einen Verfall ein."
                      type="error"></v-alert>
                  </v-col>
                </v-row>
              </v-form>


            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Statistik" color="black" v-if="itemStats.has_stats && inCheckMode">
            <v-expansion-panel-text>
              <v-container>
                <v-row>
                  <v-col cols="3" class="page-inventory__table--result">Verbrauch/Woche</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.consumption_per_week_recent.toFixed(2)} ${baseUnit}` }}
                    <LcTrend :trend="itemStats.consumption_trend" />
                  </v-col>
                  <v-col cols="3" class="page-inventory__table--result">Abweichung/Woche</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.adjustment_per_week_recent.toFixed(2)} ${baseUnit}` }}
                    <LcTrend :trend="itemStats.adjustment_trend" />
                  </v-col>
                </v-row>
                <v-row class="mt-n5">
                  <v-col cols="3" class="page-inventory__table--result">Maximalverbrauch</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.consumption_week_max_recent.toFixed(0)} ${baseUnit}` }}
                  </v-col>
                  <v-col cols="3" class="page-inventory__table--result">Standardabweichung</v-col>
                  <v-col cols="2">
                    {{ `${itemStats.consumption_week_stddev_total.toFixed(2)} ${baseUnit}` }}
                  </v-col>
                </v-row>

              </v-container>
            </v-expansion-panel-text>
          </v-expansion-panel>

        </v-expansion-panels>

        <!-- DIALOG BUTTONS -->
        <v-card class="mt-2 rounded-0" variant="outlined" :disabled="itemForm.processing">
          <v-card-text class="pa-4">

            <v-form>
              <v-row>
                <v-col cols="3" v-if="!isNewItem && inEditMode">
                  <v-btn color="error" variant="flat" block
                    @click="deleteItem">
                    Löschen
                  </v-btn>
                </v-col>
                <v-col cols="3" style="display:flex" v-if="inCheckMode">
                  <v-chip class="lastcheck-chip" prepend-icon="mdi-progress-clock">
                    <b>{{currentLastCheckedLabel}}</b></v-chip>
                </v-col>
                <v-col :cols="isNewItem ? 12 : 9">
                  <v-btn color="success" variant="flat" block :disabled="!isValidItem" :loading="itemForm.processing"
                    @click="saveItem">
                    {{ saveBtnLabel }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-form>

          </v-card-text>
        </v-card>

      </template>

      <!-- DIALOGS -->
      <LcItemSizeDialog ref="sizeDialog"
        v-model:visible="isSizeDialogVisible"
        :sizes="itemForm.sizes">
      </LcItemSizeDialog>

    </section>

  </div>

</template>
<style lang="scss" scoped>
.page-inventory {

  &__table--result-centered,
  &__table--result {
    display: flex;
    font-weight: bold;
    align-items: center;
    justify-content: right;

    &-centered {
      justify-content: center;
    }
  }

}
.lastcheck-chip {
  width: 100%;
  height: calc(var(--v-btn-height) + 0px);
}
</style>
