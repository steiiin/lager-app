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
  getters: {
    searchableItems: (state) => {
      return state.items.map(item => ({
        ...item,
        pp_name: item.name.toLowerCase(),
        pp_search_altnames: item.search_altnames ? item.search_altnames.toLowerCase() : '',
        pp_search_altnames_list: item.search_altnames?.toLowerCase().trim().replace(/^,|,$/g, '').split(',').map(tag => tag.trim()).filter(tag => tag !== ''),
        pp_search_tags: item.search_tags ? item.search_tags.toLowerCase() : '',
        pp_search_tags_list: item.search_tags?.toLowerCase().trim().replace(/^,|,$/g, '').split(',').map(tag => tag.trim()).filter(tag => tag !== ''),
      }))
    },

  },
  actions: {

    async fetchStore(force = false) {
      if ((!force && this.isLoaded) || this.loading) {
        return;
      }
      this.loading = true;
      try 
      {
        const response = await axios.get('/api/store');
        this.items = response.data.items
        this.usages = response.data.usages
        this.isLoaded = true
      } 
      catch (err) 
      {
        this.error = err;
        console.error('Failed to fetch items:', err);
      } 
      finally 
      {
        this.loading = false;
      }
    },

  },
});
