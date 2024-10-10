// Services/ItemsStore.js
import { defineStore } from 'pinia'
import { router } from '@inertiajs/vue3'
import axios from 'axios';

export const useInventoryStore = defineStore('inventory', {
  state: () => ({
    items: [],
    usages: [],
    isLoaded: false,
    loading: false,
    error: null,
  }),
  actions: {

    async fetchStore() {
      if (this.isLoaded || this.loading) {
        return;
      }
      this.loading = true;
      try {
        const response = await axios.get('/api/store');
        this.items = response.data.items
        this.usages = response.data.usages
      } catch (err) {
        this.error = err;
        console.error('Failed to fetch items:', err);
      } finally {
        this.loading = false;
      }
    },

  },
});
