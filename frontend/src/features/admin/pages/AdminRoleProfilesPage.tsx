import React, { useState } from 'react';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../shared/ui/button';
import { Badge } from '../../../shared/ui/badge';
import {
  useRoleProfiles,
  useCreateRoleProfile,
  useUpdateRoleProfile,
  useDeleteRoleProfile,
} from '../hooks';
import type { RoleProfile } from '../api';
import { LoadingSpinner } from '../../../components/shared/LoadingSpinner';
import { ProfileEditorModal } from '../components/ProfileEditorModal';

/**
 * AdminRoleProfilesPage - Admin role profiles management page
 * Round 244: Role Access Profiles
 */
export const AdminRoleProfilesPage: React.FC = () => {
  const [editingProfile, setEditingProfile] = useState<RoleProfile | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [profileToDelete, setProfileToDelete] = useState<RoleProfile | null>(null);

  const { data: profiles, isLoading, error } = useRoleProfiles();
  const createProfileMutation = useCreateRoleProfile();
  const updateProfileMutation = useUpdateRoleProfile();
  const deleteProfileMutation = useDeleteRoleProfile();

  const handleCreateProfile = async (data: {
    name: string;
    description?: string;
    roles: string[];
    is_active?: boolean;
  }) => {
    try {
      await createProfileMutation.mutateAsync(data);
      toast.success('Profile created successfully');
      setIsCreateModalOpen(false);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to create profile');
    }
  };

  const handleUpdateProfile = async (
    profileId: string,
    data: {
      name?: string;
      description?: string;
      roles?: string[];
      is_active?: boolean;
    }
  ) => {
    try {
      await updateProfileMutation.mutateAsync({ profileId, data });
      toast.success('Profile updated successfully');
      setEditingProfile(null);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to update profile');
    }
  };

  const handleDeleteProfile = async () => {
    if (!profileToDelete) return;

    try {
      await deleteProfileMutation.mutateAsync(profileToDelete.id);
      toast.success('Profile deleted successfully');
      setIsDeleteModalOpen(false);
      setProfileToDelete(null);
    } catch (error: any) {
      toast.error(error?.message || 'Failed to delete profile');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <Card>
          <CardContent className="p-6">
            <p className="text-red-600">Error loading profiles: {(error as Error).message}</p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            Role Access Profiles
          </h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            Create and manage role profiles (templates) for quick user onboarding
          </p>
        </div>
        <Button
          onClick={() => setIsCreateModalOpen(true)}
          className="bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-dark)]"
        >
          Create Profile
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Profiles</CardTitle>
        </CardHeader>
        <CardContent>
          {!profiles || profiles.length === 0 ? (
            <p className="text-[var(--color-text-secondary)] text-center py-8">
              No profiles found. Create your first profile to get started.
            </p>
          ) : (
            <div className="space-y-4">
              {profiles.map((profile) => (
                <div
                  key={profile.id}
                  className="flex items-center justify-between p-4 border border-[var(--color-border)] rounded-lg hover:bg-[var(--color-surface-muted)] transition-colors"
                >
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className="font-semibold text-[var(--color-text-primary)]">
                        {profile.name}
                      </h3>
                      {profile.is_active ? (
                        <Badge variant="default" className="text-xs">
                          Active
                        </Badge>
                      ) : (
                        <Badge variant="secondary" className="text-xs">
                          Inactive
                        </Badge>
                      )}
                    </div>
                    {profile.description && (
                      <p className="text-sm text-[var(--color-text-secondary)] mt-1">
                        {profile.description}
                      </p>
                    )}
                    <div className="flex items-center gap-2 mt-2 flex-wrap">
                      {profile.roles.length > 0 ? (
                        profile.roles.map((role) => (
                          <Badge key={role.id} variant="outline" className="text-xs">
                            {role.name}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-xs text-[var(--color-text-secondary)]">
                          No roles assigned
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-4 mt-2 text-xs text-[var(--color-text-secondary)]">
                      <span>{profile.roles.length} role(s)</span>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setEditingProfile(profile)}
                    >
                      Edit
                    </Button>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => {
                        setProfileToDelete(profile);
                        setIsDeleteModalOpen(true);
                      }}
                    >
                      Delete
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Create Profile Modal */}
      {isCreateModalOpen && (
        <ProfileEditorModal
          isOpen={isCreateModalOpen}
          onClose={() => setIsCreateModalOpen(false)}
          onSave={handleCreateProfile}
          isLoading={createProfileMutation.isPending}
        />
      )}

      {/* Edit Profile Modal */}
      {editingProfile && (
        <ProfileEditorModal
          isOpen={!!editingProfile}
          onClose={() => setEditingProfile(null)}
          onSave={(data) => handleUpdateProfile(editingProfile.id, data)}
          profile={editingProfile}
          isLoading={updateProfileMutation.isPending}
        />
      )}

      {/* Delete Confirmation Modal */}
      {isDeleteModalOpen && profileToDelete && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-md">
            <CardHeader>
              <CardTitle>Delete Profile</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-[var(--color-text-primary)] mb-4">
                Are you sure you want to delete the profile &quot;{profileToDelete.name}&quot;? This
                action cannot be undone. Note: This will not remove roles from users who already
                have this profile assigned.
              </p>
              <div className="flex justify-end gap-2">
                <Button
                  variant="outline"
                  onClick={() => {
                    setIsDeleteModalOpen(false);
                    setProfileToDelete(null);
                  }}
                >
                  Cancel
                </Button>
                <Button
                  variant="destructive"
                  onClick={handleDeleteProfile}
                  disabled={deleteProfileMutation.isPending}
                >
                  {deleteProfileMutation.isPending ? 'Deleting...' : 'Delete'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
};
