<style>
    .carbay-install-prompt { position: fixed; left: 12px; right: 12px; bottom: max(12px, env(safe-area-inset-bottom)); z-index: 50; display: flex; align-items: center; gap: 10px; padding: 14px; border: 1px solid #dce8f1; border-radius: 16px; background: white; box-shadow: 0 8px 30px #0002; color: #142637; font-size: 12px; }
    .carbay-install-prompt[hidden], .carbay-install-prompt [hidden] { display: none !important; }
    .carbay-install-prompt img { width: 36px; height: 36px; }
    .carbay-install-copy { flex: 1; }
    .carbay-install-copy strong, .carbay-install-copy span { display: block; }
    .carbay-install-prompt button { width: auto; height: auto; min-height: 40px; margin: 0; padding: 8px 12px; }
    .carbay-install-ios, .carbay-install-ios-steps { display: none !important; }
    .is-ios .carbay-install-ios, .is-ios .carbay-install-ios-steps:not([hidden]) { display: block !important; }
    .is-ios .carbay-install-android { display: none; }
</style>
