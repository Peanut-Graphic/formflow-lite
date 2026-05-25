import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link2, Plus, Trash2, Edit, RefreshCw, Settings, Zap } from 'lucide-react';
import { Card, Button, Badge, Modal, Input, Switch, ConfirmModal, SkeletonTable, InfoPanel } from '../components/common';
import { endpoints } from '../api/endpoints';
import { format } from 'date-fns';
import { useToast } from '../components/common/Toast';
import type { Connector } from '../types';

const CONNECTOR_TYPES = [
  { value: 'api', label: 'REST API', description: 'Connect to any REST API endpoint' },
  { value: 'salesforce', label: 'Salesforce', description: 'Salesforce CRM integration' },
  { value: 'hubspot', label: 'HubSpot', description: 'HubSpot marketing platform' },
  { value: 'zapier', label: 'Zapier', description: 'Connect via Zapier webhooks' },
  { value: 'custom', label: 'Custom', description: 'Custom integration endpoint' },
];

export default function Connectors() {
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingConnector, setEditingConnector] = useState<Connector | null>(null);
  const [deleteConnector, setDeleteConnector] = useState<Connector | null>(null);
  const [testingId, setTestingId] = useState<number | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    type: 'api',
    endpoint: '',
    auth_type: 'none' as 'none' | 'bearer' | 'basic' | 'api_key',
    credentials: {} as Record<string, string>,
    is_active: true,
  });
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: connectors, isLoading } = useQuery<Connector[]>({
    queryKey: ['connectors'],
    queryFn: () => endpoints.connectors.list(),
  });

  const createMutation = useMutation({
    mutationFn: (data: Partial<Connector>) => endpoints.connectors.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['connectors'] });
      toast({ type: 'success', message: 'Connector created' });
      setShowAddModal(false);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to create connector' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<Connector> }) =>
      endpoints.connectors.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['connectors'] });
      toast({ type: 'success', message: 'Connector updated' });
      setEditingConnector(null);
      setShowAddModal(false);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to update connector' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => endpoints.connectors.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['connectors'] });
      toast({ type: 'success', message: 'Connector deleted' });
      setDeleteConnector(null);
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to delete connector' });
    },
  });

  const testMutation = useMutation({
    mutationFn: (id: number) => endpoints.connectors.test(id),
    onSuccess: () => {
      toast({ type: 'success', message: 'Connection test successful' });
      setTestingId(null);
    },
    onError: () => {
      toast({ type: 'error', message: 'Connection test failed' });
      setTestingId(null);
    },
  });

  const resetForm = () => {
    setFormData({
      name: '',
      type: 'api',
      endpoint: '',
      auth_type: 'none',
      credentials: {},
      is_active: true,
    });
  };

  const handleSubmit = () => {
    const data = {
      name: formData.name,
      type: formData.type,
      endpoint: formData.endpoint,
      auth_type: formData.auth_type,
      credentials: formData.credentials,
      is_active: formData.is_active,
    };

    if (editingConnector) {
      updateMutation.mutate({ id: editingConnector.id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const getConnectorIcon = (type: string) => {
    const icons: Record<string, React.ReactNode> = {
      api: <Link2 className="w-5 h-5" />,
      salesforce: <Zap className="w-5 h-5" />,
      hubspot: <Zap className="w-5 h-5" />,
      zapier: <Zap className="w-5 h-5" />,
      custom: <Settings className="w-5 h-5" />,
    };
    return icons[type] || <Link2 className="w-5 h-5" />;
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Connectors</h1>
            <p className="text-slate-600 dark:text-slate-400">Manage external integrations</p>
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
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Connectors</h1>
          <p className="text-slate-600 dark:text-slate-400">Manage external integrations</p>
        </div>
        <Button onClick={() => setShowAddModal(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Add Connector
        </Button>
      </div>

      <InfoPanel variant="info" title="API Connectors">
        Connectors allow forms to send submission data to external systems like CRMs, marketing platforms, or custom APIs.
      </InfoPanel>

      {/* Connectors Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {connectors?.map((connector) => (
          <Card key={connector.id} className="p-4">
            <div className="flex items-start justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${
                  connector.is_active
                    ? 'bg-teal-100 dark:bg-teal-900/30 text-teal-600 dark:text-teal-400'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-400'
                }`}>
                  {getConnectorIcon(connector.type)}
                </div>
                <div>
                  <h3 className="font-medium text-slate-900 dark:text-white">{connector.name}</h3>
                  <p className="text-sm text-slate-500 dark:text-slate-400 capitalize">{connector.type}</p>
                </div>
              </div>
              <Badge variant={connector.is_active ? 'success' : 'secondary'}>
                {connector.is_active ? 'Active' : 'Inactive'}
              </Badge>
            </div>

            <div className="mb-4">
              <p className="text-sm text-slate-600 dark:text-slate-400 truncate">
                {connector.endpoint}
              </p>
              {connector.last_used && (
                <p className="text-xs text-slate-500 dark:text-slate-500 mt-1">
                  Last used: {format(new Date(connector.last_used), 'MMM d, h:mm a')}
                </p>
              )}
            </div>

            <div className="flex items-center gap-2">
              <Button
                variant="secondary"
                size="sm"
                onClick={() => {
                  setTestingId(connector.id);
                  testMutation.mutate(connector.id);
                }}
                disabled={testingId === connector.id}
              >
                {testingId === connector.id ? (
                  <RefreshCw className="w-4 h-4 animate-spin" />
                ) : (
                  <RefreshCw className="w-4 h-4" />
                )}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => {
                  setEditingConnector(connector);
                  setFormData({
                    name: connector.name,
                    type: connector.type,
                    endpoint: connector.endpoint,
                    auth_type: connector.auth_type,
                    credentials: {},
                    is_active: connector.is_active,
                  });
                  setShowAddModal(true);
                }}
              >
                <Edit className="w-4 h-4" />
              </Button>
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setDeleteConnector(connector)}
                className="text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            </div>
          </Card>
        ))}

        {(!connectors || connectors.length === 0) && (
          <Card className="col-span-full p-12 text-center">
            <Link2 className="w-12 h-12 mx-auto mb-3 text-slate-400 opacity-50" />
            <p className="font-medium text-slate-600 dark:text-slate-400">No connectors configured</p>
            <p className="text-sm text-slate-500 dark:text-slate-500 mt-1">
              Add a connector to integrate with external services
            </p>
          </Card>
        )}
      </div>

      {/* Add/Edit Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => {
          setShowAddModal(false);
          setEditingConnector(null);
          resetForm();
        }}
        title={editingConnector ? 'Edit Connector' : 'Add Connector'}
        size="lg"
      >
        <div className="space-y-4">
          <Input
            label="Name"
            value={formData.name}
            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
            placeholder="e.g., CRM Integration"
          />

          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Type</label>
            <div className="grid grid-cols-2 gap-2">
              {CONNECTOR_TYPES.map((type) => (
                <button
                  key={type.value}
                  onClick={() => setFormData({ ...formData, type: type.value })}
                  className={`p-3 text-left rounded-lg border transition-colors ${
                    formData.type === type.value
                      ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/20'
                      : 'border-slate-200 dark:border-slate-700 hover:border-slate-300'
                  }`}
                >
                  <div className="font-medium text-sm text-slate-900 dark:text-white">{type.label}</div>
                  <div className="text-xs text-slate-500 dark:text-slate-400">{type.description}</div>
                </button>
              ))}
            </div>
          </div>

          <Input
            label="Endpoint URL"
            type="url"
            value={formData.endpoint}
            onChange={(e) => setFormData({ ...formData, endpoint: e.target.value })}
            placeholder="https://api.example.com/endpoint"
          />

          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="connectors-authentication">Authentication</label>
            <select id="connectors-authentication"
              value={formData.auth_type}
              onChange={(e) => setFormData({ ...formData, auth_type: e.target.value as typeof formData.auth_type })}
              className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              <option value="none">No Authentication</option>
              <option value="bearer">Bearer Token</option>
              <option value="basic">Basic Auth</option>
              <option value="api_key">API Key</option>
            </select>
          </div>

          {formData.auth_type === 'bearer' && (
            <Input
              label="Bearer Token"
              type="password"
              value={formData.credentials.token || ''}
              onChange={(e) => setFormData({
                ...formData,
                credentials: { ...formData.credentials, token: e.target.value },
              })}
              placeholder="Enter your bearer token"
            />
          )}

          {formData.auth_type === 'basic' && (
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Username"
                value={formData.credentials.username || ''}
                onChange={(e) => setFormData({
                  ...formData,
                  credentials: { ...formData.credentials, username: e.target.value },
                })}
              />
              <Input
                label="Password"
                type="password"
                value={formData.credentials.password || ''}
                onChange={(e) => setFormData({
                  ...formData,
                  credentials: { ...formData.credentials, password: e.target.value },
                })}
              />
            </div>
          )}

          {formData.auth_type === 'api_key' && (
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Header Name"
                value={formData.credentials.header || ''}
                onChange={(e) => setFormData({
                  ...formData,
                  credentials: { ...formData.credentials, header: e.target.value },
                })}
                placeholder="e.g., X-API-Key"
              />
              <Input
                label="API Key"
                type="password"
                value={formData.credentials.key || ''}
                onChange={(e) => setFormData({
                  ...formData,
                  credentials: { ...formData.credentials, key: e.target.value },
                })}
              />
            </div>
          )}

          <Switch
            checked={formData.is_active}
            onChange={(checked) => setFormData({ ...formData, is_active: checked })}
            label="Active"
            description="Enable this connector"
          />

          <div className="flex gap-3 pt-4">
            <Button onClick={handleSubmit} className="flex-1" disabled={!formData.name || !formData.endpoint}>
              {editingConnector ? 'Save Changes' : 'Create Connector'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setShowAddModal(false);
                setEditingConnector(null);
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
        isOpen={!!deleteConnector}
        onClose={() => setDeleteConnector(null)}
        onConfirm={() => deleteConnector && deleteMutation.mutate(deleteConnector.id)}
        title="Delete Connector"
        message={`Are you sure you want to delete "${deleteConnector?.name}"? Forms using this connector will no longer be able to send data to this endpoint.`}
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={deleteMutation.isPending}
      />
    </div>
  );
}
