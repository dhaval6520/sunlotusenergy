jQuery(function ($) {
    "use strict";

    var autoSaveTimeout;

    function arrayIntersect(a, b) {
        var t;
        if (b.length > a.length) {
            t = b;
            b = a;
            a = t;
        }
        return a.filter(function (e) {
            return b.indexOf(e) > -1;
        });
    }

    function activateEditorPageSettingsSection(section) {
        window.$e.route("panel/page-settings/settings");
        window.elementor.getPanelView().currentPageView.activateSection(section)._renderChildren();
    }

    function getControlsOverlay(controls) {
        var controlsHTML = controls.reduce(function (s, e) {
            return s + "<li class=\"the7-elementor-element-setting the7-elementor-element-setting-" + e.action + "\" title=\"" + e.title + "\">" +
                "<i class=\"" + e.icon + "\" aria-hidden=\"true\"></i>" +
                "<span class=\"elementor-screen-only\">" + e.title + "</span>" +
                "</li>";
        }, "");
        controlsHTML = "<div class=\"the7-elementor-overlay\"><ul class=\"the7-elementor-element-settings\">" + controlsHTML + "</ul></div>";

        return $(controlsHTML);
    }

    function removeAllControls() {
        var iframe = $("#elementor-preview-iframe").first().contents();
        var $the7overlays = $(".the7-elementor-overlay-active", iframe);
        $the7overlays.find(".the7-elementor-overlay").remove();
        $the7overlays.removeClass("the7-elementor-overlay-active");
    }

    function addControls($el, controls) {
        var $controlsOverlay;

        controls = controls.filter(function (control) {
            return !control.section || elementor.settings.page.model.controls[control.section];
        });

        if (!controls) {
            return;
        }

        $controlsOverlay = getControlsOverlay(controls);

        controls.forEach(function (control) {
            if (control.events) {
                var events = control.events;
                var $control = $controlsOverlay.find(".the7-elementor-element-setting-" + control.action);
                for (var event in events) {
                    $control.on(event, events[event]);
                }
            }
        });

        $el.addClass("the7-elementor-overlay-active");
        $el.append($controlsOverlay);
    }

    elementor.on("document:loaded", function (document) {
        var iframe = $("#elementor-preview-iframe").first().contents();

        removeAllControls();

        var $elementorEditor = $(".elementor-editor-active #content > .elementor", iframe);
        var $elementorHeaderEditor = $(".elementor-editor-active #page > .elementor-location-header", iframe);

        $(".transparent.title-off #page > .masthead", iframe).hover(
            function () {
                $elementorEditor.children(".elementor-document-handle").addClass("visible");
                $elementorHeaderEditor.children(".elementor-document-handle").addClass("visible");
            },
            function () {
                $elementorEditor.children(".elementor-document-handle").removeClass("visible");
                $elementorHeaderEditor.children(".elementor-document-handle").removeClass("visible");
            }
        );
        var $elemntorEditorFooter = $("body.elementor-editor-footer")[0];
        var $elemntorEditorHeader = $("body.elementor-editor-header")[0];
        if (($elemntorEditorFooter === undefined) && ($elemntorEditorHeader === undefined)) {
            addControls($("#sidebar", iframe), [
                {
                    action: "edit",
                    title: "Edit Sidebar",
                    icon: "eicon-edit",
                    section: "the7_document_sidebar",
                    events: {
                        click: function () {
                            activateEditorPageSettingsSection("the7_document_sidebar");

                            return false;
                        }
                    }
                }
            ]);

            if ($("#footer.elementor-footer", iframe)[0] === undefined) {
                addControls($("#footer > .wf-wrap > .wf-container-footer", iframe), [
                    {
                        action: "edit",
                        title: "Edit Footer",
                        icon: "eicon-edit",
                        section: "the7_document_footer",
                        events: {
                            click: function () {
                                activateEditorPageSettingsSection("the7_document_footer");

                                return false;
                            }
                        }
                    }
                ]);
            }
        }
        if ($elemntorEditorFooter === undefined) {
            var $elemntorLocationHeader = $(".elementor-location-header", iframe)[0];
            if (($elemntorLocationHeader !== undefined && $elemntorEditorHeader !== undefined) || (
                $elemntorLocationHeader === undefined && $elemntorEditorHeader === undefined)) {
                addControls($(".masthead, .page-title, #main-slideshow, #fancy-header", iframe), [
                    {
                        action: "edit",
                        title: "Edit Title",
                        icon: "eicon-edit",
                        section: "the7_document_title_section",
                        events: {
                            click: function () {
                                activateEditorPageSettingsSection("the7_document_title_section");

                                return false;
                            }
                        }
                    }
                ]);
            }
        }

        elementor.settings.page.model.on("change", function (settings) {
            var iframe = $("#elementor-preview-iframe").first().contents();
            var the7Settings = arrayIntersect(Object.keys(settings.changed), the7Elementor.controlsIds);

            var tobBarColor = settings.changed.the7_document_disabled_header_top_bar_color || settings.changed.the7_document_fancy_header_top_bar_color;
            var headerBgColor = settings.changed.the7_document_disabled_header_backgraund_color || settings.changed.the7_document_fancy_header_backgraund_color;

            if (tobBarColor !== undefined) {
                $(".top-bar .top-bar-bg", iframe).css("background-color", tobBarColor);
            }

            if (headerBgColor !== undefined) {
                $(".masthead.inline-header, .masthead.classic-header, .masthead.split-header, .masthead.mixed-header", iframe).css("background-color", headerBgColor);
            }

            clearTimeout(autoSaveTimeout);
            if (the7Settings.length > 0) {
                autoSaveTimeout = setTimeout(function () {
                    elementor.saver.saveAutoSave({
                        onSuccess: function onSuccess() {
                            elementor.reloadPreview();
                            elementor.once("preview:loaded", function () {
                                if (!settings.controls[the7Settings[0]]) {
                                    return;
                                }
                                setTimeout(function () {
                                    activateEditorPageSettingsSection(settings.controls[the7Settings[0]].section);
                                });
                            });
                        }
                    });
                }, 300);
            } else {
                //handle kit reload
                var page_name = elementor.getPanelView().getCurrentPageName();
                if (page_name !== "kit_settings") {
                    return
                }
                var name = Object.keys(settings.changed)[0];
                if (name in settings.controls) {
                    var control = settings.controls[name];
                    if ('the7_reload_on_change' in control && control['the7_reload_on_change'] === true) {
                        const activeTab = elementor.getPanelView().getCurrentPageView().content.currentView.activeTab;
                        const activeSection = elementor.getPanelView().getCurrentPageView().content.currentView.activeSection;

                        autoSaveTimeout = setTimeout(function () {
                            $e.internal('panel/state-loading');
                            jQuery('#elementor-preview-loading').show();
                            elementor.saver.update.apply().then(function () {
                                $e.run('editor/documents/switch', {
                                    mode: 'autosave',
                                    id: elementor.config.initial_document.id,
                                    onClose: function onClose(document) {
                                        //$e.components.get('panel/global').close();
                                        if (document.isDraft()) {
                                            // Restore published style.
                                            elementor.toggleDocumentCssFiles(document, true);
                                            elementor.settings.page.destroyControlsCSS();
                                        }
                                        // The kit shouldn't be cached for next open. (it may be changed via create colors/typography).
                                        elementor.documents.invalidateCache( elementor.config.kit_id );
                                    }
                                }).finally(function () {
                                    elementor.reloadPreview();
                                 //   elementor.initElements();
                                    elementor.once("preview:loaded", function () {
                                        $e.run('editor/documents/switch', {
                                            id: elementor.config.kit_id,
                                            mode: 'autosave',
                                        }).finally(function () {
                                            $e.route('panel/global/' + activeTab);
                                            elementor.getPanelView().currentPageView.content.currentView.activateSection(activeSection)._renderChildren();
                                            $e.internal('panel/state-ready');
                                            jQuery( '#elementor-loading, #elementor-preview-loading' ).fadeOut( 600 );
                                        });
                                    });
                                });
                            });
                        }, 300);
                    }
                }
            }
        });
        /*elementor.settings.page.addChangeCallback("the7_scroll_to_top_button_icon", function (newValue) {
            elementor.saver.update.apply().then(function () {
                elementor.reloadPreview();
            });
        });*/
    });
});

(function ($) {
    "use strict";

    $(window).on("elementor:init", function () {
        class The7AfterSave extends $e.modules.hookData.After {
            getCommand() {
                return 'document/save/save';
            }

            getConditions(args) {
                /**
                 * Conditions was copied from elementor code base.
                 * Search for 'document/save/save' in elementor/assets/js/editor.js
                 */
                const status = args.status,
                    _args$document = args.document,
                    document = _args$document === void 0 ? elementor.documents.getCurrent() : _args$document;
                return 'publish' === status && 'kit' === document.config.type;
            }

            getId() {
                return 'the7-saver-after-save';
            }

            apply(args) {
                const settings = args.document.container.settings;
                jQuery.each(settings.changed, function (key) {
                    if (settings !== 'undefined' && settings.controls !== 'undefined' && 'the7_save' in settings.controls[key] && settings.controls[key]['the7_save'] === true) {
                        if (key in elementor.settings.page.model.controls && key in settings.attributes) {
                            elementor.settings.page.model.controls[key].default = settings.attributes[key];
                        }
                    }
                });
            }
        }

        // Change default values in order to fix settings saving.
        $e.hooks.registerDataAfter(new The7AfterSave());
    });
})(jQuery);
