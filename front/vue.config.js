const path = require("path");

module.exports = {
    publicPath: '/inc/auth/',
    devServer: {
        host: '0.0.0.0',
        port: 8080,
        public: 'localhost:8080',
        disableHostCheck: true,
        https: true
    },
    outputDir: path.resolve(__dirname, './../../../../public/inc/auth'),
    configureWebpack: {
        output: {
            path: path.resolve(__dirname, './../../../../public/inc/auth'),
            filename: '[name].js',
            chunkFilename: '[name].js'
        }
    },
    chainWebpack: config => {
        config
            .plugin('provide')
            .use(require('webpack').ProvidePlugin, [{
                Main: path.resolve(path.join(__dirname, 'Main.js')),
            }])
        if(config.plugins.has('extract-css')) {
            const extractCSSPlugin = config.plugin('extract-css')
            extractCSSPlugin && extractCSSPlugin.tap(() => [{
                filename: '[name].css',
                chunkFilename: '[name].css'
            }])
        }
    }
}
