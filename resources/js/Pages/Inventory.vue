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
  import { ref, computed, nextTick, toRef, onMounted, onUnmounted } from 'vue'
  import { Head, router, useForm } from '@inertiajs/vue3'

  // Local composables
  import { useBaseSize } from '@/Composables/useBaseSize'
  import { useOptimalSize } from '@/Composables/useOptimalSize'
  import { useInventoryStore } from '@/Services/StoreService'
  import InputService from '@/Services/InputService'

  import { useIsPwa } from '@/Composables/useIsPwa'
  const { isPwa } = useIsPwa()

  // Local components
  import LcPagebar from '@/Components/LcPagebar.vue'
  import LcItemInput from '@/Components/LcItemInput.vue'
  import LcButtonGroup from '@/Components/LcButtonGroup.vue'
  import LcItemSizeDialog from '@/Dialogs/LcItemSizeDialog.vue'
  import LcItemExpiryDialog from '@/Dialogs/LcItemExpiryDialog.vue'

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

  // #region KeyboardShortcuts

    const handleEsc = () => {
      if (isItemSelected.value) {
        clearSelectedItem()
      } else {
        openWelcome()
      }
    }
    const handleEnter = () => {
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value && !isExpiryDialogVisible.value) {
        saveItem()
      }
    }

     const isEditableTarget = () => {
      const activeElement = document.activeElement
      if (!activeElement) { return false }
      const tag = activeElement.tagName
      return activeElement.isContentEditable || [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes(tag)
    }

    const handleLeft = () => {
      if (isEditableTarget()) { return }
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value && !isExpiryDialogVisible.value) {
        itemForm.current_quantity -= 1
      }
    }
    const handleRight = () => {
      if (isEditableTarget()) { return }
      if (isItemSelected.value && !isSizeDialogVisible.value && !isStockDialogVisible.value && !isExpiryDialogVisible.value) {
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
      editorAccordionOpened.value = [ 4, 5, 6 ]
    }

  // #endregion

  // #region Dashboard

    // Methods
    const locationCollator = new Intl.Collator('de', {
      sensitivity: 'base',
      numeric: true,
    })
    const normLocationPart = v => v ?? ''
    const locationFallbackOrderRank = Number.MAX_SAFE_INTEGER
    const roomOrder = [
      'Lagerraum',
      'Fahrzeuge',
      'Desiraum',
      'Küche',
    ]
    const cabOrder = [
      'Kühlschrank',
      'Schrank (links)',
      'Schrank (rechts)',
      'Regal',
      'Schubladenschrank',
      'Hängeschrank (links)',
      'Hängeschrank (rechts)',
      'Schrank li. neben der Spüle',
    ]
    const roomOrderMap = new Map(roomOrder.map((name, index) => [name, index]))
    const cabOrderMap = new Map(cabOrder.map((name, index) => [name, index]))

    const compareByLocation = (a, b) => {
      const la = a.location ?? {}
      const lb = b.location ?? {}
      const roomRankA = roomOrderMap.get(la.room) ?? locationFallbackOrderRank
      const roomRankB = roomOrderMap.get(lb.room) ?? locationFallbackOrderRank
      const cabRankA = cabOrderMap.get(la.cab) ?? locationFallbackOrderRank
      const cabRankB = cabOrderMap.get(lb.cab) ?? locationFallbackOrderRank

      return (
        (roomRankA - roomRankB) ||
        (roomRankA === locationFallbackOrderRank && roomRankB === locationFallbackOrderRank
          ? locationCollator.compare(normLocationPart(la.room), normLocationPart(lb.room))
          : 0) ||
        (cabRankA - cabRankB) ||
        (cabRankA === locationFallbackOrderRank && cabRankB === locationFallbackOrderRank
          ? locationCollator.compare(normLocationPart(la.cab), normLocationPart(lb.cab))
          : 0) ||
        locationCollator.compare(normLocationPart(la.exact), normLocationPart(lb.exact)) ||
        locationCollator.compare(normLocationPart(a.name), normLocationPart(b.name))
      )
    }

    const getConsumptionPerWeek = (item) => {
      const hasRecentStats = Number(item.weeks_recent ?? 0) > 0
      const recent = Number(item.consumption_per_week_recent ?? 0)
      const total = Number(item.consumption_per_week_total ?? 0)

      return hasRecentStats ? recent : total
    }

    const hasStrictStockPressure = (item) => {
      const maxStock = Number(item.max_stock ?? 0)
      const consumptionPerWeek = getConsumptionPerWeek(item)
      const consumptionWeekMaxRecent = Number(item.consumption_week_max_recent ?? 0)
      return maxStock <= (
        2 * consumptionPerWeek > maxStock ||
        consumptionWeekMaxRecent > maxStock ||
        item.is_problem_item === true
      )
    }

    const parseDate = (dateString) => {
      if (!dateString) { return null }
      const date = new Date(dateString)
      return isNaN(date) ? null : date
    }

    const getTodayStart = () => {
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      return today
    }

    const isCheckedToday = (item) => {
      const checkedAt = parseDate(item.checked_at)
      if (!checkedAt) { return false }

      return checkedAt >= getTodayStart()
    }

    const getExpiryCheckThreshold = () => {
      const threshold = new Date()
      threshold.setMonth(threshold.getMonth() + 2)
      threshold.setHours(23, 59, 59, 999)
      return threshold
    }

    const getCheckPriority = (item) => {
      // if (item.tags.some(tag => tag.type == 'expiry')) { return 0 }
      return 1
    }

    const findCheckTags = (item) => {

      // check      (if check is necessary)   'Noch Nie'|'12.12.2025'
      // expiry     (if expiry is near)       'Kein MHD'|'12-2025'
      // strict_check  (if hasSTrictStockPressure)

      const normalTimespan = (new Date()); normalTimespan.setDate(normalTimespan.getDate() - 60); normalTimespan.setHours(0, 0, 0, 0);
      const strictTimespan = (new Date()); strictTimespan.setDate(strictTimespan.getDate() - 14); strictTimespan.setHours(0, 0, 0, 0);

      const tags = []

      if (!item.checked_at) { tags.push({ type: 'check', label: 'Noch Nie' }) }
      else {

        const checked_at = parseDate(item.checked_at)
        const belowNormal = checked_at !== null && checked_at <= normalTimespan
        const belowStrict = checked_at !== null && checked_at <= strictTimespan

        if (belowNormal) { tags.push({ type: 'check', label: getLastCheckedLabel(item.checked_at) }) }
        if (belowStrict && hasStrictStockPressure(item)) {
          if (!tags.some(tag => tag.type == 'check')) {
            tags.push({ type: 'check', label: getLastCheckedLabel(item.checked_at) })
          }
          tags.push({ type: 'strict_check' })
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

      const inventoryExpiryDate = getInventoryExpiryDate(item)
      if (inventoryExpiryDate && !isCheckedToday(item)) {
        const inventoryExpiry = parseDate(inventoryExpiryDate)
        const thresholdExpiry = getExpiryCheckThreshold()
        if (inventoryExpiry !== null && inventoryExpiry <= thresholdExpiry) {
          tags.push({ type: 'expiry', label: getExpiryLabel(inventoryExpiryDate) })
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
          return (
            getCheckPriority(a) - getCheckPriority(b) ||
            compareByLocation(a, b)
          )
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

      const itemsToCheck = itemsCheckNecessary.value.length > 0
        ? [ ...itemsCheckNecessary.value ].sort(compareByLocation)
        : [ ...inventoryStore.items ]
          .filter(item => item.current_quantity < item.max_stock)
          .sort((a, b) => a.current_quantity - b.current_quantity)

      checkAllList.value = itemsToCheck.map(item => item.id)

      clearSelectedItem(true)
    }

    const modifiedVehicleExpiryItems = computed(() => {
      return [ ...inventoryStore.items ]
        .filter(item => (item.expiry_entries ?? []).some(entry => (
          entry.usage_id !== null
          && entry.is_modified === true
        )))
        .sort(compareByLocation)
    })

    const hasModifiedVehicleExpiryItems = computed(() => modifiedVehicleExpiryItems.value.length > 0)

    const checkAllVehicleExpiryItems = () => {
      const itemsToCheck = modifiedVehicleExpiryItems.value
      checkAllList.value = itemsToCheck.map(item => item.id)
      clearSelectedItem(true)
    }

    const checkAllLowStockItems = () => {

      const itemsToCheck = [ ...inventoryStore.items ]
        .filter(item => Number(item.onvehicle_stock ?? 0) > Number(item.max_stock ?? 0))
        .sort(compareByLocation)

      checkAllList.value = itemsToCheck.map(item => item.id)

      clearSelectedItem(true)
    }

    const checkAllItems = () => {

      const todayStart = getTodayStart()

      const itemsToCheck = [ ...inventoryStore.items ]
        .filter((item) => {
          if (!item.checked_at) { return true }
          const checkedAt = parseDate(item.checked_at)
          return checkedAt === null || checkedAt < todayStart
        })
        .sort(compareByLocation)

      checkAllList.value = itemsToCheck.map(item => item.id)

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
      current_quantity: 0,
      onvehicle_stock: 0,
      checked_at: null,
      max_order_quantity: 0,
      max_bookin_quantity: 0,
      dont_order: false,

      sizes: [],
      expiry_entries: [],

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

      itemForm.current_quantity = 0
      itemForm.checked_at = null
      itemForm.max_order_quantity = 0
      itemForm.max_bookin_quantity = 0
      itemForm.dont_order = false

      itemForm.sizes.splice(0, itemForm.sizes.length)
      itemForm.sizes.push({ id: null, unit: 'Stk.', amount: 1, is_default: true })
      itemForm.expiry_entries.splice(0, itemForm.expiry_entries.length)

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

      itemForm.current_quantity = item.current_quantity
      itemForm.checked_at = !item.checked_at ? null : new Date(item.checked_at)
      itemForm.max_order_quantity = item.max_order_quantity
      itemForm.max_bookin_quantity = item.max_bookin_quantity
      itemForm.dont_order = item.dont_order ?? false

      itemForm.sizes = (item.sizes ?? []).map((size) => ({ ...size }))
      itemForm.expiry_entries = item.expiry_entries ?? []
      itemForm.stockchangeReason = -1

      editorAccordionOpened.value = inCheckMode.value ? [ 4, 5, 6 ] : []
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

      const getInventoryExpiryDate = (item) => {
        const stockExpiryEntry = [ ...(item.expiry_entries ?? []) ]
          .filter(entry => entry.usage_id === null && entry.status === 'reserved' && Number(entry.expiryQuantity ?? 0) > 0 && !!entry.expiryAt)
          .sort((a, b) => new Date(a.expiryAt) - new Date(b.expiryAt))[0] ?? null

        return stockExpiryEntry?.expiryAt ?? null
      }

      const isSizeDialogVisible = ref(false)
      const isStockDialogVisible = ref(false)
      const isExpiryDialogVisible = ref(false)

    // #endregion

    // #region Sizes-Group

      const sizesRef = toRef(itemForm, 'sizes')
      const minStockRef = toRef(itemForm, 'min_stock')
      const maxStockRef = toRef(itemForm, 'max_stock')

      const { baseUnit } = useBaseSize(sizesRef)
      const { text: minStockOptimalText } = useOptimalSize(sizesRef, minStockRef)
      const { text: maxStockOptimalText } = useOptimalSize(sizesRef, maxStockRef)

      const getStockSizeText = (stock, optimalText) => {
        const baseText = baseUnit.value ? `${stock ?? 0} ${baseUnit.value}` : `${stock ?? 0}`
        if (!optimalText || optimalText === baseText) { return baseText }
        return `${baseText} bzw. ${optimalText}`
      }

      const minStockTitleText = computed(() => getStockSizeText(itemForm.min_stock, minStockOptimalText.value))
      const maxStockTitleText = computed(() => getStockSizeText(itemForm.max_stock, maxStockOptimalText.value))

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
    // #region Quantity-Group

      const selectableStockChangeReasons = ref([
        { name: "Abweichung", value: -1 },
        { name: "Rückbuchung", value: -2 },
        { name: "Verfall", value: -3 },
      ])

    // #endregion
    // #region Expiry-Group

      const expiryDialog = ref(null)
      const createExpiry = (usageId = null) => expiryDialog.value?.create(itemForm.id, usageId)
      const editExpiry = (item) => expiryDialog.value?.edit(item)

      const expiryHeaders = ref([
        { title: 'Verwendung', key: 'usage_name', minWidth: '25%' },
        { title: 'Verfall', key: 'expiryAt', minWidth: '15%' },
        { title: 'Menge', key: 'expiryQuantityLabel', minWidth: '15%' },
        { title: 'Bestellt', key: 'is_ordered', minWidth: '12%' },
        { title: '', key: 'is_modified', align: 'center', width: '3rem', sortable: false },
        { title: 'Notiz', key: 'note', minWidth: '20%' },
        { title: 'Bearbeiten', key: 'action', sortable: false },
      ])
      const expirySortBy = ref([
        { key: 'expiryAt', order: 'asc' }
      ])

      const getUsageName = (entry) => {
        return entry.usage?.name
          ?? inventoryStore.usages.find(usage => usage.id === entry.usage_id)?.name
          ?? 'Lagerbestand'
      }

      const getExpiryTableItems = computed(() => {
        return itemForm.expiry_entries.map(entry => ({
          ...entry,
          usage_name: getUsageName(entry),
          expiryQuantityLabel: entry.usage_id === null ? 'Gesamtbestand' : entry.expiryQuantity,
        }))
      })
      const expiryUsageOptions = computed(() => [
        { id: null, name: 'Lagerbestand' },
        ...inventoryStore.usages.filter(usage => usage.could_expire),
      ])
      const hasStockExpiry = computed(() => {
        return itemForm.expiry_entries.some(entry => (
          entry.usage_id === null
          && entry.status === 'reserved'
          && Number(entry.expiryQuantity ?? 0) > 0
          && !!entry.expiryAt
        ))
      })
      const visibleExpiryAddOptions = computed(() => {
        return expiryUsageOptions.value
      })

      const refreshCurrentItemFromStore = () => {
        const freshItem = inventoryStore.items.find(item => item.id === itemForm.id)
        if (!freshItem) return
        itemForm.expiry_entries = freshItem.expiry_entries ?? []
      }

      const saveExpiry = async (entry) => {
        const payload = {
          item_id: entry.item_id,
          usage_id: entry.usage_id,
          expiryAt: entry.expiryAt,
          expiryQuantity: entry.expiryQuantity,
          status: entry.status,
          is_ordered: entry.is_ordered ?? false,
          is_modified: false,
          note: entry.note,
        }

        if (entry.id) {
          await axios.put(`/api/item-expiry/${entry.id}`, payload)
        } else {
          await axios.post('/api/item-expiry', payload)
        }

        await inventoryStore.fetchStore(true)
        refreshCurrentItemFromStore()
      }

      const deleteExpiry = async (entry) => {
        if (!entry.id || !confirm('Willst du das wirklich löschen?')) { return }

        await axios.delete(`/api/item-expiry/${entry.id}`)
        await inventoryStore.fetchStore(true)
        refreshCurrentItemFromStore()
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
    inventoryStore.fetchStore(true)
    document.body.classList.remove('cursor-off')
  })
  onUnmounted(() => {
    InputService.unregisterLeft(handleLeft)
    InputService.unregisterRight(handleRight)
    InputService.unregisterEsc(handleEsc)
    InputService.unregisterEnter(handleEnter)
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

      </template>

      <div v-show="!isItemSelected">
        <LcItemInput
          :result-specs="{ w: 850, i: 19.0 }" :allow-new="inEditMode" :disabled="isItemSelected"
          preserve-search-on-select
          @create-new="createNew" @select-item="editItem">
        </LcItemInput>
      </div>

      <template v-if="!isItemSelected">

        <!-- CheckBoad -->
        <v-card class="mt-2" variant="outlined" v-show="inCheckMode">
          <v-list-item class="px-6" height="88">
            <template v-slot:prepend>
              <v-icon icon="mdi-clipboard-clock"></v-icon>
            </template>
            <template v-slot:title> <b>Checklisten</b> </template>
            <template v-slot:append>
              <v-btn v-if="inCheckMode"
                class="text-none"
                color="primary"
                text="KFZ-VERFALL"
                variant="text"
                slim
                :disabled="!hasModifiedVehicleExpiryItems"
                @click="checkAllVehicleExpiryItems"
              ></v-btn>
              <v-btn v-if="inCheckMode"
                class="text-none"
                color="primary"
                text="GERING-BESTAND"
                variant="text"
                slim @click="checkAllLowStockItems"
              ></v-btn>
              <v-btn v-if="inCheckMode"
                class="text-none"
                color="primary"
                text="ALLES"
                variant="text"
                slim @click="checkAllItems"
              ></v-btn>
            </template>
          </v-list-item>
        </v-card>
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
                text="PRÜFE LISTE"
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
            <div class="title-with-minmax">
              <b>{{ editorTitle }}</b>
              <p v-if="inCheckMode">
                Min: {{ minStockTitleText }}<br>
                Max: {{ maxStockTitleText }}
              </p>
            </div>
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

              <v-checkbox
                v-model="itemForm.dont_order"
                class="mt-2"
                label="Bestellung vorrübergehend aussetzen"
                color="warning"
                hide-details>
              </v-checkbox>

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

              <v-alert v-if="itemForm.onvehicle_stock >= itemForm.max_stock"
                style="margin:1rem 0"
                text="Der Fahrzeugbestand übersteigt den Lagerbestand."
                title="Achtung"
                type="warning"
                variant="tonal"
              />

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
              </v-form>


            </v-expansion-panel-text>
          </v-expansion-panel>

          <v-expansion-panel class="mt-1" title="Verfall" color="black">
            <v-expansion-panel-text>

              <v-alert
                v-if="isNewItem"
                text="Speichere den Artikel zuerst, bevor du Verfälle hinzufügst."
                type="info"
                variant="tonal"
              />

              <template v-else>

                <v-data-table
                  :items="getExpiryTableItems"
                  :headers="expiryHeaders"
                  :sort-by="expirySortBy"
                  hide-default-footer
                  :items-per-page="100"
                  no-data-text="Kein Verfall erfasst"
                >
                  <template v-slot:item.expiryAt="{ item }">
                    {{ getExpiryLabel(item.expiryAt) }}
                  </template>
                  <template v-slot:item.note="{ item }">
                    {{ item.note || '-' }}
                  </template>
                  <template v-slot:item.is_ordered="{ item }">
                    <v-chip
                      v-if="item.is_ordered"
                      color="primary"
                      size="small"
                      variant="tonal"
                    >
                      Ja
                    </v-chip>
                    <span v-else>-</span>
                  </template>
                  <template v-slot:header.is_modified>
                    <v-icon
                      icon="mdi-alert"
                      size="small"
                      v-tooltip:bottom="'Nach Buchung geändert'"
                    ></v-icon>
                  </template>
                  <template v-slot:item.is_modified="{ item }">
                    <v-chip
                      v-if="item.is_modified"
                      color="warning"
                      size="x-small"
                      variant="tonal"
                    >
                      Ja
                    </v-chip>
                    <span v-else>-</span>
                  </template>
                  <template v-slot:item.action="{ item }">
                    <v-btn small variant="outlined" @click="editExpiry(item)">
                      <v-icon icon="mdi-cog"></v-icon>
                    </v-btn>
                  </template>
                </v-data-table>

                <v-divider></v-divider>

                <div class="page-inventory__expiry-add mt-4">
                  <v-btn
                    v-for="usage in visibleExpiryAddOptions"
                    :key="usage.id ?? 'stock'"
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-plus"
                    @click="createExpiry(usage.id)"
                  >
                    {{ usage.name }}
                  </v-btn>
                </div>
              </template>

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

      <LcItemExpiryDialog ref="expiryDialog"
        v-model:visible="isExpiryDialogVisible"
        :usages="inventoryStore.usages"
        @save="saveExpiry"
        @delete="deleteExpiry">
      </LcItemExpiryDialog>

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

  &__expiry-add {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

}
.lastcheck-chip {
  width: 100%;
  height: calc(var(--v-btn-height) + 0px);
}

.title-with-minmax {

  display: flex;
  align-items: center;
  gap: .5rem;

  & p
  {
    font-size: 0.7em;
    line-height: 1;
  }

}

</style>
