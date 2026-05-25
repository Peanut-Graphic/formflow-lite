import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Webhook, Plus, Trash2, Edit, Play, ExternalLink } from 'lucide-react';
import { Card, Button, Badge, Modal, Input, Switch, ConfirmModal, SkeletonTable, InfoPanel } from '../components/common';
import { endpoints } from '../api/endpoints';
import { format } from 'date-fns';
import { useToast } from '../components/common/Toast';
import type { Webhook as WebhookType, FormInstance } from '../types';

const EVENTS = [
  { value: 'submission.created', label: 'Submission Created' },
  { value: 'submission.updated', label: 'Submission Updated' },
  { value: 'submission.deleted', label: 'Submission Deleted' },
  { value: 'form.created', label: 'Form Created' },
  { value: 'form.updated', label: 'Form Updated' },
];

export default function Webhooks() {
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingWebhook, setEditingWebhook] = useState<WebhookType | null>(null);
  const [deleteWebhook, setDeleteWebhook] = useState<WebhookType | null>(null);
  const [testingId, setTestingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    url: '',
    events: ['submission.created'] as string[],
    form_id: '' as string | number,
    secret: '',
    is_active: true,
  });
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: forms } = useQuery<FormInstance[]>({
    queryKey: ['forms'],
    queryFn: () => endpoints.forms.list(),
  });

  const { data: webhooks, isLoading } = useQuery<WebhookType[]>({
    queryKey: ['webhooks'],
    queryFn: () => endpoints.webhooks.list(),
  });

  const createMutation = useMutation({
    mutationFn: (data: Partial<WebhookType>) => endpoints.webhooks.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webhooks'] });
      toast({ type: 'success', message: 'Webhook created' });
      setShowAddModal(false);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to create webhook' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<WebhookType> }) =>
      endpoints.webhooks.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webhooks'] });
      toast({ type: 'success', message: 'Webhook updated' });
      setEditingWebhook(null);
      setShowAddModal(false);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to update webhook' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => endpoints.webhooks.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['webhooks'] });
      toast({ type: 'success', message: 'Webhook deleted' });
      setDeleteWebhook(null);
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to delete webhook' });
    },
  });

  const testMutation = useMutation({
    mutationFn: (id: number) => endpoints.webhooks.test(id),
    onSuccess: () => {
      toast({ type: 'success', message: 'Test payload sent successfully' });
      setTestingId(null);
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to send test payload' });
      setTestingId(null);
    },
  });

  const resetForm = () => {
    setFormData({
      name: '',
      url: '',
      events: ['submission.created'],
      form_id: '',
      secret: '',
      is_active: true,
    });
  };

  const handleSubmit = () => {
    const data = {
      name: formData.name,
      url: formData.url,
      events: formData.events,
      form_id: formData.form_id ? Number(formData.form_id) : undefined,
      secret: formData.secret || undefined,
      is_active: formData.is_active,
    };

    if (editingWebhook) {
      updateMutation.mutate({ id: editingWebhook.id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const toggleEvent = (event: string) => {
    setFormData((prev) => ({
      ...prev,
      events: prev.events.includes(event)
        ? prev.events.filter((e) => e !== event)
        : [...prev.events, event],
    }));
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Webhooks</h1>
            <p className="text-slate-600 dark:text-slate-400">Configure webhook integrations</p>
          </div>
        </div>
        <SkeletonTable rows={5} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Webhooks</h1>
          <p className="text-slate-600 dark:text-slate-400">Configure webhook integrations</p>
        </div>
        <Button onClick={() => setShowAddModal(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Add Webhook
        </Button>
      </div>

      <InfoPanel variant="info" title="Webhook Payloads">
        Webhooks send JSON payloads to your specified URL when events occur. Use the secret key to verify payloads using HMAC-SHA256.
      </InfoPanel>

      {/* Webhooks List */}
      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-700">
                <th className="text-left py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">Webhook</th>
                <th className="text-left py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">URL</th>
                <th className="text-left py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">Events</th>
                <th className="text-left py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">Status</th>
                <th className="text-left py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">Last Triggered</th>
                <th className="text-right py-3 px-4 text-sm font-medium text-slate-500 dark:text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody>
              {webhooks?.map((webhook) => (
                <tr key={webhook.id} className="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                        <Webhook className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                      </div>
                      <div>
                        <div className="font-medium text-slate-900 dark:text-white">{webhook.name}</div>
                        {webhook.form_id && (
                          <div className="text-sm text-slate-500 dark:text-slate-400">
                            Form: {forms?.find((f) => f.id === webhook.form_id)?.name || `#${webhook.form_id}`}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex items-center gap-2">
                      <code className="text-sm text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded max-w-[200px] truncate">
                        {webhook.url}
                      </code>
                      <a href={webhook.url} target="_blank" rel="noopener noreferrer" className="text-slate-400 hover:text-slate-600">
                        <ExternalLink className="w-4 h-4" />
                      </a>
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex flex-wrap gap-1">
                      {webhook.events.slice(0, 2).map((event) => (
                        <Badge key={event} variant="secondary" className="text-xs">
                          {event.split('.')[1]}
                        </Badge>
                      ))}
                      {webhook.events.length > 2 && (
                        <Badge variant="secondary" className="text-xs">+{webhook.events.length - 2}</Badge>
                      )}
                    </div>
                  </td>
                  <td className="py-3 px-4">
                    <Badge variant={webhook.is_active ? 'success' : 'secondary'}>
                      {webhook.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </td>
                  <td className="py-3 px-4 text-sm text-slate-600 dark:text-slate-400">
                    {webhook.last_triggered
                      ? format(new Date(webhook.last_triggered), 'MMM d, h:mm a')
                      : 'Never'}
                  </td>
                  <td className="py-3 px-4">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => {
                          setTestingId(webhook.id);
                          testMutation.mutate(webhook.id);
                        }}
                        disabled={testingId === webhook.id || !webhook.is_active}
                        className="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors disabled:opacity-50"
                        title="Test webhook"
                      >
                        <Play className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => {
                          setEditingWebhook(webhook);
                          setFormData({
                            name: webhook.name,
                            url: webhook.url,
                            events: webhook.events,
                            form_id: webhook.form_id || '',
                            secret: '',
                            is_active: webhook.is_active,
                          });
                          setShowAddModal(true);
                        }}
                        className="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                        title="Edit"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => setDeleteWebhook(webhook)}
                        className="p-2 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        title="Delete"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {(!webhooks || webhooks.length === 0) && (
                <tr>
                  <td colSpan={6} className="py-12 text-center text-slate-500 dark:text-slate-400">
                    <Webhook className="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p className="font-medium">No webhooks configured</p>
                    <p className="text-sm mt-1">Add a webhook to send data to external services</p>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Add/Edit Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => {
          setShowAddModal(false);
          setEditingWebhook(null);
          resetForm();
        }}
        title={editingWebhook ? 'Edit Webhook' : 'Add Webhook'}
        size="lg"
      >
        <div className="space-y-4">
          <Input
            label="Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            placeholder="e.g., CRM Integration"
          />

          <Input
            label="URL"
            type="url"
            value={formData.url}
            onChange={(e) => setFormData({ ...formData, url: e.target.value })}
            placeholder="https://api.example.com/webhook"
          />

          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Events</label>
            <div className="space-y-2">
              {EVENTS.map((event) => (
                <label key={event.value} className="flex items-center gap-3 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={formData.events.includes(event.value)}
                    onChange={() => toggleEvent(event.value)}
                    className="w-4 h-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                  />
                  <span className="text-sm text-slate-700 dark:text-slate-300">{event.label}</span>
                </label>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="webhooks-form-optional">
              Form (Optional)
            </label>
            <select id="webhooks-form-optional"
              value={formData.form_id}
              onChange={(e) => setFormData({ ...formData, form_id: e.target.value })}
              className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              <option value="">All forms</option>
              {forms?.map((form) => (
                <option key={form.id} value={form.id}>{form.name}</option>
              ))}
            </select>
            <p className="mt-1 text-xs text-slate-500">Leave empty to receive events from all forms</p>
          </div>

          <Input
            label="Secret Key (Optional)"
            type="password"
            value={formData.secret}
            onChange={(e) => setFormData({ ...formData, secret: e.target.value })}
            placeholder="For HMAC signature verification"
          />

          <Switch
            checked={formData.is_active}
            onChange={(checked) => setFormData({ ...formData, is_active: checked })}
            label="Active"
            description="Enable this webhook to receive events"
          />

          <div className="flex gap-3 pt-4">
            <Button onClick={handleSubmit} className="flex-1" disabled={!formData.name || !formData.url || formData.events.length === 0}>
              {editingWebhook ? 'Save Changes' : 'Create Webhook'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setShowAddModal(false);
                setEditingWebhook(null);
                resetForm();
              }}
            >
              Cancel
            </Button>
          </div>
        </div>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={!!deleteWebhook}
        onClose={() => setDeleteWebhook(null)}
        onConfirm={() => deleteWebhook && deleteMutation.mutate(deleteWebhook.id)}
        title="Delete Webhook"
        message={`Are you sure you want to delete "${deleteWebhook?.name}"? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={deleteMutation.isPending}
      />
    </div>
  );
}
