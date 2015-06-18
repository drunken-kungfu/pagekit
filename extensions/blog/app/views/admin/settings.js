module.exports = {

    data: function () {
        return window.$data;
    },

    methods: {

        save: function () {
            this.$http.post('admin/system/settings/config', { name: 'blog', config: this.config }, function () {
                UIkit.notify(this.$trans('Settings saved.'), '');
            }).error(function (data) {
                UIkit.notify(data, 'danger');
            });
        }

    }

};

$(function () {

    new Vue(module.exports).$mount('#settings');

});
