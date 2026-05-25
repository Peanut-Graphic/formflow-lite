import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Settings as SettingsIcon, Save, Bell, Shield, Palette } from 'lucide-react';
import { Card, Button, Input, Switch, Skeleton, InfoPanel } from '../components/common';
import { endpoints } from '../api/endpoints';
import { useToast } from '../components/common/Toast';
import type { Settings as SettingsType } from '../types';

export default function Settings() {
  const [settings, setSettings] = useState<SettingsType>({
    general: {
      default_status: 'draft',
      submissions_per_page: 20,
      enable_analytics: true,
      enable_logging: true,
    },
    notifications: {
      email_notifications: true,
      admin_email: '',
      notify_on_submission: true,
      notify_on_error: true,
    },
    security: {
      honeypot_enabled: true,
      rate_limiting: true,
      rate_limit_per_minute: 10,
      require_consent: false,
      consent_text: '',
    },
    styling: {
      use_theme_styles: true,
      custom_css: '',
    },
  });
  const [hasChanges, setHasChanges] = useState(false);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: savedSettings, isLoading } = useQuery<SettingsType>({
    queryKey: ['settings'],
    queryFn: () => endpoints.settings.get(),
  });

  useEffect(() => {
    if (savedSettings) {
      setSettings(savedSettings);
    }
  }, [savedSettings]);

  const saveMutation = useMutation({
    mutationFn: (data: SettingsType) => endpoints.settings.update(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['settings'] });
      toast({ type: 'success', message: 'Settings saved successfully' });
      setHasChanges(false);
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to save settings' });
    },
  });

  const updateSettings = <K extends keyof SettingsType>(
    section: K,
    key: keyof SettingsType[K],
    value: SettingsType[K][keyof SettingsType[K]]
  ) => {
    setSettings((prev) => ({
      ...prev,
      [section]: {
        ...prev[section],
        [key]: value,
      },
    }));
    setHasChanges(true);
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Settings</h1>
            <p className="text-slate-600 dark:text-slate-400">Configure FormFlow behavior</p>
          </div>
        </div>
        <Card className="p-6">
          <div className="space-y-4">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="flex items-center justify-between">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-6 w-12" />
              </div>
            ))}
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Settings</h1>
          <p className="text-slate-600 dark:text-slate-400">Configure FormFlow behavior</p>
        </div>
        <Button
          onClick={() => saveMutation.mutate(settings)}
          disabled={!hasChanges || saveMutation.isPending}
        >
          <Save className="w-4 h-4 mr-2" />
          {saveMutation.isPending ? 'Saving...' : 'Save Changes'}
        </Button>
      </div>

      {hasChanges && (
        <InfoPanel variant="warning" title="Unsaved Changes">
          You have unsaved changes. Click "Save Changes" to apply them.
        </InfoPanel>
      )}

      {/* General Settings */}
      <Card>
        <div className="flex items-center gap-3 p-4 border-b border-slate-200 dark:border-slate-700">
          <div className="w-10 h-10 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-center">
            <SettingsIcon className="w-5 h-5 text-teal-600 dark:text-teal-400" />
          </div>
          <div>
            <h3 className="font-medium text-slate-900 dark:text-white">General Settings</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">Basic form configuration</p>
          </div>
        </div>
        <div className="p-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="settings-default-form-status">
              Default Form Status
            </label>
            <select id="settings-default-form-status"
              value={settings.general.default_status}
              onChange={(e) => updateSettings('general', 'default_status', e.target.value as 'draft' | 'active')}
              className="w-full max-w-xs px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              <option value="draft">Draft</option>
              <option value="active">Active</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="settings-submissions-per-page">
              Submissions Per Page
            </label>
            <input id="settings-submissions-per-page"
              type="number"
              min="10"
              max="100"
              value={settings.general.submissions_per_page}
              onChange={(e) => updateSettings('general', 'submissions_per_page', Number(e.target.value))}
              className="w-full max-w-xs px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            />
          </div>
          <Switch
            checked={settings.general.enable_analytics}
            onChange={(checked) => updateSettings('general', 'enable_analytics', checked)}
            label="Enable Analytics"
            description="Track form views and submission rates"
          />
          <Switch
            checked={settings.general.enable_logging}
            onChange={(checked) => updateSettings('general', 'enable_logging', checked)}
            label="Enable Activity Logging"
            description="Log form and submission events"
          />
        </div>
      </Card>

      {/* Notification Settings */}
      <Card>
        <div className="flex items-center gap-3 p-4 border-b border-slate-200 dark:border-slate-700">
          <div className="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
            <Bell className="w-5 h-5 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <h3 className="font-medium text-slate-900 dark:text-white">Notifications</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">Email and alert settings</p>
          </div>
        </div>
        <div className="p-4 space-y-4">
          <Switch
            checked={settings.notifications.email_notifications}
            onChange={(checked) => updateSettings('notifications', 'email_notifications', checked)}
            label="Email Notifications"
            description="Send email notifications for form events"
          />
          {settings.notifications.email_notifications && (
            <>
              <Input
                label="Admin Email"
                type="email"
                value={settings.notifications.admin_email}
                onChange={(e) => updateSettings('notifications', 'admin_email', e.target.value)}
                placeholder="admin@example.com"
              />
              <Switch
                checked={settings.notifications.notify_on_submission}
                onChange={(checked) => updateSettings('notifications', 'notify_on_submission', checked)}
                label="Notify on New Submission"
                description="Receive an email when a form is submitted"
              />
              <Switch
                checked={settings.notifications.notify_on_error}
                onChange={(checked) => updateSettings('notifications', 'notify_on_error', checked)}
                label="Notify on Errors"
                description="Receive an email when submission errors occur"
              />
            </>
          )}
        </div>
      </Card>

      {/* Security Settings */}
      <Card>
        <div className="flex items-center gap-3 p-4 border-b border-slate-200 dark:border-slate-700">
          <div className="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
            <Shield className="w-5 h-5 text-red-600 dark:text-red-400" />
          </div>
          <div>
            <h3 className="font-medium text-slate-900 dark:text-white">Security</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">Spam protection and rate limiting</p>
          </div>
        </div>
        <div className="p-4 space-y-4">
          <Switch
            checked={settings.security.honeypot_enabled}
            onChange={(checked) => updateSettings('security', 'honeypot_enabled', checked)}
            label="Honeypot Protection"
            description="Add invisible spam trap field to forms"
          />
          <Switch
            checked={settings.security.rate_limiting}
            onChange={(checked) => updateSettings('security', 'rate_limiting', checked)}
            label="Rate Limiting"
            description="Limit submissions per IP address"
          />
          {settings.security.rate_limiting && (
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="settings-max-submissions-per-minute">
                Max Submissions Per Minute
              </label>
              <input id="settings-max-submissions-per-minute"
                type="number"
                min="1"
                max="100"
                value={settings.security.rate_limit_per_minute}
                onChange={(e) => updateSettings('security', 'rate_limit_per_minute', Number(e.target.value))}
                className="w-full max-w-xs px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
          )}
          <Switch
            checked={settings.security.require_consent}
            onChange={(checked) => updateSettings('security', 'require_consent', checked)}
            label="Require Consent"
            description="Require users to consent before submitting"
          />
          {settings.security.require_consent && (
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="settings-consent-text">
                Consent Text
              </label>
              <textarea id="settings-consent-text"
                value={settings.security.consent_text}
                onChange={(e) => updateSettings('security', 'consent_text', e.target.value)}
                rows={3}
                placeholder="I agree to the terms and conditions..."
                className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
          )}
        </div>
      </Card>

      {/* Styling Settings */}
      <Card>
        <div className="flex items-center gap-3 p-4 border-b border-slate-200 dark:border-slate-700">
          <div className="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
            <Palette className="w-5 h-5 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <h3 className="font-medium text-slate-900 dark:text-white">Styling</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">Form appearance settings</p>
          </div>
        </div>
        <div className="p-4 space-y-4">
          <Switch
            checked={settings.styling.use_theme_styles}
            onChange={(checked) => updateSettings('styling', 'use_theme_styles', checked)}
            label="Use Theme Styles"
            description="Inherit styles from your WordPress theme"
          />
          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="settings-custom-css">
              Custom CSS
            </label>
            <textarea id="settings-custom-css"
              value={settings.styling.custom_css}
              onChange={(e) => updateSettings('styling', 'custom_css', e.target.value)}
              rows={6}
              placeholder=".formflow-form { /* your styles */ }"
              className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-teal-500"
            />
          </div>
        </div>
      </Card>
    </div>
  );
}
