import Vue from 'vue'
import Vuetify from 'vuetify'
import 'vuetify/dist/vuetify.min.css'

Vue.use(Vuetify);

export default new Vuetify({
    theme: {
        themes: {
            dark: {
                // primary: '#607D8B',
            },
            light: {
                primary: '#009688',
                // secondary: '#FFF',
                // accent: '#FFF',
            },
        }
    },
    icons: {
        iconfont: 'mdi',
    },
});
