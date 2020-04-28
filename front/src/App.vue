<template>
    <v-app id="inspire">
        <v-snackbar v-model="snack.isShow">{{ snack.text }}</v-snackbar>
        <v-content>
            <v-container fluid fill-height>
                <v-layout align-center justify-center>
                    <v-flex xs12 sm8 md5>
                        <login-page v-if="config.pageName === 'login'"/>
                        <signup-page v-else-if="config.pageName === 'signup'"/>
                        <recovering-init-page v-else-if="config.pageName === 'initRecovering'"/>
                        <recovering-finish-page v-else-if="config.pageName === 'finishRecovering'"/>
                        <twofa-page v-else-if="config.pageName === 'twofa'"/>
                        <twofa-login-confirm-page v-else-if="config.pageName === 'twofaLoginConfirmation'"/>
                        <oauth-page v-else-if="config.pageName === 'oauth'"/>
                    </v-flex>
                </v-layout>
            </v-container>
        </v-content>
    </v-app>
</template>

<script>
    import LoginPage from './page/LoginPage';
    import SignupPage from './page/SignupPage';
    import RecoveringInitPage from './page/RecoveringInitPage';
    import RecoveringFinishPage from './page/RecoveringFinishPage';
    import TwofaPage from './page/TwofaPage';
    import TwofaLoginConfirmPage from './page/TwofaLoginConfirmPage';
    import OauthPage from './page/OauthPage';

    export default {
        components: {
            LoginPage,
            SignupPage,
            RecoveringInitPage,
            RecoveringFinishPage,
            TwofaPage,
            OauthPage,
            TwofaLoginConfirmPage
        },
        data: () => ({
            config: config,
            snack: {isShow: false, text: ''},
        }),
        beforeMount() {
            this.$snack.listener = function (text) {
                this.snack.text = text;
                this.snack.isShow = false;
                this.snack.isShow = true;
            }.bind(this);
        },
    }
</script>
