import Vue from 'vue'
import Vuetify from 'vuetify/lib'
import 'vuetify/dist/vuetify.min.css'

Vue.use(Vuetify);

export default new Vuetify({
    theme: {
        themes: {
            dark: {
                // primary: '#607D8B',
            },
            light: {
                primary: '#0177fd',
                secondary: '#45aaf2',
                // accent: '#FFF',
            },
        }
    },
    icons: {
        iconfont: 'mdi',
    },
});
