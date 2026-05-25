import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Calendar, Clock, Plus, Trash2, Edit } from 'lucide-react';
import { Card, Button, Badge, Modal, Input, Switch, ConfirmModal, SkeletonTable, InfoPanel } from '../components/common';
import { endpoints } from '../api/endpoints';
import { useToast } from '../components/common/Toast';
import type { ScheduleSlot, FormInstance } from '../types';

export default function Scheduling() {
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingSlot, setEditingSlot] = useState<ScheduleSlot | null>(null);
  const [deleteSlot, setDeleteSlot] = useState<ScheduleSlot | null>(null);
  const [formData, setFormData] = useState({
    form_id: '',
    day_of_week: '1',
    start_time: '09:00',
    end_time: '17:00',
    max_submissions: '50',
    is_active: true,
  });
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const { data: forms } = useQuery<FormInstance[]>({
    queryKey: ['forms'],
    queryFn: () => endpoints.forms.list(),
  });

  const { data: slots, isLoading } = useQuery<ScheduleSlot[]>({
    queryKey: ['scheduling'],
    queryFn: () => endpoints.scheduling.list(),
  });

  const createMutation = useMutation({
    mutationFn: (data: Partial<ScheduleSlot>) => endpoints.scheduling.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['scheduling'] });
      toast({ type: 'success', message: 'Schedule slot created' });
      setShowAddModal(false);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to create schedule slot' });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<ScheduleSlot> }) =>
      endpoints.scheduling.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['scheduling'] });
      toast({ type: 'success', message: 'Schedule slot updated' });
      setEditingSlot(null);
      resetForm();
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to update schedule slot' });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => endpoints.scheduling.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['scheduling'] });
      toast({ type: 'success', message: 'Schedule slot deleted' });
      setDeleteSlot(null);
    },
    onError: () => {
      toast({ type: 'error', message: 'Failed to delete schedule slot' });
    },
  });

  const resetForm = () => {
    setFormData({
      form_id: '',
      day_of_week: '1',
      start_time: '09:00',
      end_time: '17:00',
      max_submissions: '50',
      is_active: true,
    });
  };

  const handleSubmit = () => {
    const data = {
      form_id: Number(formData.form_id),
      day_of_week: Number(formData.day_of_week),
      start_time: formData.start_time,
      end_time: formData.end_time,
      max_submissions: Number(formData.max_submissions),
      is_active: formData.is_active,
    };

    if (editingSlot) {
      updateMutation.mutate({ id: editingSlot.id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  const slotsByDay = dayNames.map((day, index) => ({
    day,
    slots: slots?.filter((s) => s.day_of_week === index) || [],
  }));

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Scheduling</h1>
            <p className="text-slate-600 dark:text-slate-400">Manage form availability windows</p>
          </div>
        </div>
        <SkeletonTable rows={7} />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Scheduling</h1>
          <p className="text-slate-600 dark:text-slate-400">Manage form availability windows</p>
        </div>
        <Button onClick={() => setShowAddModal(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Add Time Slot
        </Button>
      </div>

      <InfoPanel variant="tip" title="Scheduling Tips">
        Schedule specific time windows when forms can accept submissions. Useful for demand response programs where enrollment needs to be controlled.
      </InfoPanel>

      {/* Schedule Grid */}
      <div className="space-y-4">
        {slotsByDay.map(({ day, slots: daySlots }) => (
          <Card key={day}>
            <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-teal-100 dark:bg-teal-900/30 rounded-lg flex items-center justify-center">
                  <Calendar className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                </div>
                <h3 className="font-medium text-slate-900 dark:text-white">{day}</h3>
              </div>
              <Badge variant={daySlots.length > 0 ? 'success' : 'secondary'}>
                {daySlots.length} slot{daySlots.length !== 1 ? 's' : ''}
              </Badge>
            </div>
            {daySlots.length > 0 ? (
              <div className="divide-y divide-slate-100 dark:divide-slate-700/50">
                {daySlots.map((slot) => {
                  const form = forms?.find((f) => f.id === slot.form_id);
                  return (
                    <div key={slot.id} className="flex items-center justify-between p-4">
                      <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2 text-sm">
                          <Clock className="w-4 h-4 text-slate-400" />
                          <span className="font-medium text-slate-900 dark:text-white">
                            {slot.start_time} - {slot.end_time}
                          </span>
                        </div>
                        <span className="text-slate-400">•</span>
                        <span className="text-sm text-slate-600 dark:text-slate-400">
                          {form?.name || `Form #${slot.form_id}`}
                        </span>
                        <span className="text-slate-400">•</span>
                        <span className="text-sm text-slate-500 dark:text-slate-400">
                          Max: {slot.max_submissions}
                        </span>
                        <Badge variant={slot.is_active ? 'success' : 'secondary'}>
                          {slot.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </div>
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => {
                            setEditingSlot(slot);
                            setFormData({
                              form_id: String(slot.form_id),
                              day_of_week: String(slot.day_of_week),
                              start_time: slot.start_time,
                              end_time: slot.end_time,
                              max_submissions: String(slot.max_submissions),
                              is_active: slot.is_active,
                            });
                            setShowAddModal(true);
                          }}
                          className="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                        >
                          <Edit className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => setDeleteSlot(slot)}
                          className="p-2 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">
                No time slots scheduled
              </div>
            )}
          </Card>
        ))}
      </div>

      {/* Add/Edit Modal */}
      <Modal
        isOpen={showAddModal}
        onClose={() => {
          setShowAddModal(false);
          setEditingSlot(null);
          resetForm();
        }}
        title={editingSlot ? 'Edit Time Slot' : 'Add Time Slot'}
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="scheduling-form">Form</label>
            <select id="scheduling-form"
              value={formData.form_id}
              onChange={(e) => setFormData({ ...formData, form_id: e.target.value })}
              className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              <option value="">Select a form</option>
              {forms?.map((form) => (
                <option key={form.id} value={form.id}>{form.name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="scheduling-day-of-week">Day of Week</label>
            <select id="scheduling-day-of-week"
              value={formData.day_of_week}
              onChange={(e) => setFormData({ ...formData, day_of_week: e.target.value })}
              className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
            >
              {dayNames.map((day, i) => (
                <option key={day} value={i}>{day}</option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="scheduling-start-time">Start Time</label>
              <input id="scheduling-start-time"
                type="time"
                value={formData.start_time}
                onChange={(e) => setFormData({ ...formData, start_time: e.target.value })}
                className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1" htmlFor="scheduling-end-time">End Time</label>
              <input id="scheduling-end-time"
                type="time"
                value={formData.end_time}
                onChange={(e) => setFormData({ ...formData, end_time: e.target.value })}
                className="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>
          </div>

          <Input
            label="Max Submissions"
            type="number"
            value={formData.max_submissions}
            onChange={(e) => setFormData({ ...formData, max_submissions: e.target.value })}
          />

          <Switch
            checked={formData.is_active}
            onChange={(checked) => setFormData({ ...formData, is_active: checked })}
            label="Active"
            description="Enable this time slot"
          />

          <div className="flex gap-3 pt-4">
            <Button onClick={handleSubmit} className="flex-1" disabled={!formData.form_id}>
              {editingSlot ? 'Save Changes' : 'Create Slot'}
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setShowAddModal(false);
                setEditingSlot(null);
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
        isOpen={!!deleteSlot}
        onClose={() => setDeleteSlot(null)}
        onConfirm={() => deleteSlot && deleteMutation.mutate(deleteSlot.id)}
        title="Delete Time Slot"
        message="Are you sure you want to delete this time slot? Forms will no longer be restricted during this time."
        confirmLabel="Delete"
        confirmVariant="danger"
        loading={deleteMutation.isPending}
      />
    </div>
  );
}
