<template>
    <user-form title="Двухфакторная аутентификация" submitText="Сохранить" url="/2fa/enroll" @success="success"
               :addFormData="addFormData">
        <template v-slot:default="slotProps">
            <v-tabs v-model="tab" @change="changeTab(slotProps.form)" style="padding-bottom:10px;" centered
                    icons-and-text>
                <v-tabs-slider></v-tabs-slider>

                <v-tab key="1">
                    Telegram
                    <v-icon>mdi-telegram</v-icon>
                </v-tab>

                <v-tab key="2">
                    Google
                    <v-icon>mdi-google</v-icon>
                </v-tab>
            </v-tabs>
            <v-tabs-items v-model="tab">
                <v-tab-item key="1">
                    <tab-telegram :form="slotProps.form"/>
                </v-tab-item>
                <v-tab-item key="2">
                    <tab-google :form="slotProps.form"/>
                </v-tab-item>
            </v-tabs-items>
        </template>
        <template v-slot:actions>
            <v-btn href="/exit" text>Выйти</v-btn>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './UserForm';
    import TabTelegram from './TabTelegram';
    import TabGoogle from './TabGoogle';

    export default {
        components: {UserForm, TabTelegram, TabGoogle},
        data: () => ({
            config: config,
            tab: null,
            addFormData: {type: 1, context: null},
        }),
        methods: {
            changeTab(form) {
                form.reset();
                let tabKey_twofaType_map = {0: 'telegram', 1: 'google',};
                let type = tabKey_twofaType_map[this.tab];
                this.addFormData.type = type;
                if (type === 'google') {
                    this.addFormData.context = config.googleSecret;
                } else {
                    this.addFormData.context = null;
                }
            },
            success() {
                this.$snack.success({text: 'Сохранено, переадресация', button: 'close'});
                window.location.href = '/private';
            },
        }
    }
</script>
