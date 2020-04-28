import './scss/main.scss';
import './scss/vue-snack.css';

import Vue from 'vue';
import App from './App.vue';
import VueResource from 'vue-resource';
import vuetify from '@/plugins/vuetify';

let snack = {
    install(Vue) {
        Vue.prototype.$snack = {
            listener: null,
            success(data) {
                if (null !== this.listener) {
                    this.listener(data.text);
                }
            },
            danger(data) {
                return this.success(data);
            }
        }
    }
}

Vue.use(VueResource);
Vue.use(snack);
Vue.config.productionTip = false;

new Vue({
    render: (h) => h(App),
    vuetify,
}).$mount('#app');
