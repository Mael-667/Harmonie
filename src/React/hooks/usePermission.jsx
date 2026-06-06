import { createContext, useCallback, useContext, useMemo } from 'react';

const PermissionContext = createContext(null);

const Permission = {
    Read: "read",
    Write: "write",
    Edit: "edit",
    Delete: "delete",
};

export function PermissionProvider({ userId, roles, children }) {
    // une "méthode" exposée par le contexte
    const hasServerRight = useCallback((permission = Permission.Read) => {
        if (!roles) return false;
        for (let i = 0; i < roles.length; ++i) {
            if (roles[i].members.includes(userId) || roles[i].members.includes("*")) {
                if (roles[i].serverPermission.includes("*") || roles[i].serverPermission.includes(permission)) {
                    return true;
                }
            }
        }
        return false;
    }, [roles, userId]);                     // ← ne change QUE si roles ou userId change

    // on met données ET méthodes dans la value
    const value = useMemo(
          () => ({ Permission, hasServerRight }),
          [hasServerRight]                     // Permission est stable (constante module)
      );

    return (
        <PermissionContext value={value}>
            {children}
        </PermissionContext>
    );
}

export const usePermission = () => useContext(PermissionContext);