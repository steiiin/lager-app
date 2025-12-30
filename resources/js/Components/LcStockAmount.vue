<template>

  <!-- Dialog -->
  <LcItemAmountDialog ref="stockDialog" />

  <!-- Row -->
  <v-row>
    <v-col cols="5" class="lc-stock-amount__cell">
      <v-btn prepend-icon="mdi-calculator"
        variant="outlined" :text="buttonText"
        @click="openStockDialog">
      </v-btn>
    </v-col>

    <v-col cols="2" class="lc-stock-amount__result">
      {{ defaultStockText }}
    </v-col>

    <v-col cols="2" class="lc-stock-amount__result" v-if="stockSizesDiffer">
      bzw. {{ stockText }}
    </v-col>
  </v-row>

</template>

<script setup>
import { computed, ref, toRef } from 'vue'
import LcItemAmountDialog from '@/Dialogs/LcItemAmountDialog.vue'
import { useBaseSize } from '@/Composables/useBaseSize'
import { useOptimalSize } from '@/Composables/useOptimalSize'

const props = defineProps({
  sizes: {
    type: Array,
    required: true,
  },
  stock: {
    type: Number,
    required: true,
  },

  title: {
    type: String,
    default: 'Bestand berechnen',
  },
  message: {
    type: String,
    default: 'Gib eine Packungsgröße und eine Menge ein, um einen neuen Bestand zu errechnen.',
  },
  buttonText: {
    type: String,
    default: 'Bestand ändern',
  },

  visible: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:stock', 'update:visible'])

const stockDialog = ref(null)

const sizesRef = toRef(props, 'sizes')
const stockRef = toRef(props, 'stock')

// Your composables, now driven by props
const { baseUnit } = useBaseSize(sizesRef)
const { text: stockText } = useOptimalSize(sizesRef, stockRef)

const defaultStockText = computed(() => `${props.stock} ${baseUnit.value}`)
const stockSizesDiffer = computed(() => stockText.value !== defaultStockText.value)

const defaultSelectedSize = computed(() => props.sizes?.find(e => e?.is_default))

const openStockDialog = async () => {
  if (!stockDialog.value?.open) {
    throw new Error('LcItemstockDialog ref is not ready or has no open() method.')
  }

  emit('update:visible', true)
  const newStock = await stockDialog.value.open({
    title: props.title,
    message: props.message,
    sizes: props.sizes,
    selectedSize: defaultSelectedSize.value,
  })
  emit('update:visible', false)

  if (newStock === null) return
  emit('update:stock', newStock)
}
</script>
<style lang="scss">
.lc-stock-amount {

  &__cell {
    padding: 12px 0 0 0;
  }

  &__result {
    display: flex;
    font-weight: bold;
    align-items: center;
    justify-content: left;

    &-centered {
      justify-content: center;
    }
  }

}
</style>