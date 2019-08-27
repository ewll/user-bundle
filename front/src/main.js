import './scss/main.scss';
import './scss/vue-snack.css';

import Vue from 'vue';
import App from './App.vue';
import VueResource from 'vue-resource';
import vuetify from '@/plugins/vuetify';
import VueSnackbar from 'vue-snack';

Vue.use(VueResource);
Vue.use(VueSnackbar, {position: 'bottom', time: 6000});
Vue.config.productionTip = false;

new Vue({
    render: (h) => h(App),
    vuetify,
}).$mount('#app');
