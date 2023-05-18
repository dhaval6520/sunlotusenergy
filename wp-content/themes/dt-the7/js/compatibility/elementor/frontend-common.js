(function ($) {
    // Make sure you run this code under Elementor.
    $(window).on("elementor/frontend/init", function () {
        The7ElementorSettings = function ($el) {
            this.$widget = $el;
            // Private methods
            var methods = {
                getID: function ($widget) {
                    return $widget.data('id');
                },
                getModelCID: function ($widget) {
                    return  $widget.data('model-cid');
                },
                getItems: function (items, itemKey) {
                    if (itemKey) {
                        const keyStack = itemKey.split('.'),
                            currentKey = keyStack.splice(0, 1);

                        if (!keyStack.length) {
                            return items[currentKey];
                        }

                        if (!items[currentKey]) {
                            return;
                        }

                        return methods.getItems(items[currentKey], keyStack.join('.'));
                    }

                    return items;
                }
            };
            The7ElementorSettings.prototype.getWidgetType = function () {
                const widgetType = this.$widget.data('widget_type');
                if (!widgetType) {
                    return null;
                }
                return widgetType.split('.')[0];
            };
            The7ElementorSettings.prototype.getID = function () {
                return methods.getID(this.$widget);
            };

            The7ElementorSettings.prototype.getModelCID = function () {
                return methods.getModelCID(this.$widget);
            };

            The7ElementorSettings.prototype.getCurrentDeviceSetting = function (settingKey) {
                return elementorFrontend.getCurrentDeviceSetting(this.getSettings(), settingKey);
            };

            The7ElementorSettings.prototype.getSettings = function (setting) {
                var elementSettings = {};
                const modelCID = methods.getModelCID(this.$widget);
                if (modelCID) {
                    const settings = elementorFrontend.config.elements.data[modelCID],
                        attributes = settings.attributes;

                    var type = attributes.widgetType || attributes.elType;

                    if (attributes.isInner) {
                        type = 'inner-' + type;
                    }

                    var settingsKeys = elementorFrontend.config.elements.keys[type];

                    if (!settingsKeys) {
                        settingsKeys = elementorFrontend.config.elements.keys[type] = [];

                        $.each(settings.controls, function (name) {
                            if (this.frontend_available) {
                                settingsKeys.push(name);
                            }
                        });
                    }

                    $.each(settings.getActiveControls(), function (controlKey) {
                        if (-1 !== settingsKeys.indexOf(controlKey)) {
                            var value = attributes[controlKey];

                            if (value.toJSON) {
                                value = value.toJSON();
                            }

                            elementSettings[controlKey] = value;
                        }
                    });
                } else {
                    elementSettings = this.$widget.data('settings') || {};
                }
                return methods.getItems(elementSettings, setting);
            };
        };

        The7ElementorSettings.getResponsiveSettingList = function (setting) {
            const breakpoints = Object.keys(elementorFrontend.config.responsive.activeBreakpoints);
            return ['', ...breakpoints].map(suffix => {
                return suffix ? `${setting}_${suffix}` : setting;
            });
        };

        /**
         * Get Control Value
         *
         * Retrieves a control value.
         *
         * @param {{}}     setting A settings object (e.g. element settings - keys and values)
         * @param {string} controlKey      The control key name
         * @param {string} controlSubKey   A specific property of the control object.
         * @return {*} Control Value
         */
        The7ElementorSettings.getControlValue = function  (setting, controlKey, controlSubKey) {
            let value;
            if ('object' === typeof setting[controlKey] && controlSubKey) {
                value = setting[controlKey][controlSubKey];
            } else {
                value = setting[controlKey];
            }
            return value;
        }

        /**
         * Get the value of a responsive control.
         *
         * Retrieves the value of a responsive control for the current device or for this first parent device which has a control value.
         *
         *
         * @param {{}}     setting A settings object (e.g. element settings - keys and values)
         * @param {string} controlKey      The control key name
         * @param {string} controlSubKey   A specific property of the control object.
         * @param {string} device          If we want to get a value for a specific device mode.
         * @return {*} Control Value
         */
        The7ElementorSettings.getResponsiveControlValue = function (setting, controlKey) {
            let controlSubKey = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : '';
            let device = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : null;
            const currentDeviceMode = device || elementorFrontend.getCurrentDeviceMode(),
                controlValueDesktop = The7ElementorSettings.getControlValue(setting, controlKey, controlSubKey);

            // Set the control value for the current device mode.
            // First check the widescreen device mode.
            if ('widescreen' === currentDeviceMode) {
                const controlValueWidescreen = The7ElementorSettings.getControlValue(setting, `${controlKey}_widescreen`, controlSubKey);
                return !!controlValueWidescreen || 0 === controlValueWidescreen ? controlValueWidescreen : controlValueDesktop;
            }

            // Loop through all responsive and desktop device modes.
            const activeBreakpoints = elementorFrontend.breakpoints.getActiveBreakpointsList({
                withDesktop: true
            });
            let parentDeviceMode = currentDeviceMode,
                deviceIndex = activeBreakpoints.indexOf(currentDeviceMode),
                controlValue = '';
            while (deviceIndex <= activeBreakpoints.length) {
                if ('desktop' === parentDeviceMode) {
                    controlValue = controlValueDesktop;
                    break;
                }
                const responsiveControlKey = `${controlKey}_${parentDeviceMode}`,
                    responsiveControlValue = The7ElementorSettings.getControlValue(setting, responsiveControlKey, controlSubKey);
                if (!!responsiveControlValue || 0 === responsiveControlValue) {
                    controlValue = responsiveControlValue;
                    break;
                }

                // If no control value has been set for the current device mode, then check the parent device mode.
                deviceIndex++;
                parentDeviceMode = activeBreakpoints[deviceIndex];
            }
            return controlValue;
        }

    });
})(jQuery);
