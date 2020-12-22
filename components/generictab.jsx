import { api_list, api_misc } from "./api.js";
import { DataTable } from 'primereact/components/datatable/DataTable';
import { Column } from 'primereact/components/column/Column';
import { Dropdown } from 'primereact/dropdown';
import { InputText } from 'primereact/inputtext';
import { Paginator } from 'primereact/paginator';
import { Toast } from 'primereact/toast';

import React from 'react';
import PagedTab from './pagedtab';

const fieldToSorterList={
    "id":"i",
    "name":"n"
};

export default class GenericTab extends PagedTab {
    constructor(props, context) {
        super(props, context);
    }

    apiCall = (o,p,f,s) => {
        api_list('template','item',{ sort: s, offset: o, pagesize: p, filter: { type: this.props.template.name, name: f }});
    }

    fieldToSorter = (fld) => {
        return fieldToSorterList[fld];
    }

    toastMessage = (type,item) => {
        if(type == "save") {
            return { severity: 'info', summary: 'Item Saved', detail: 'Item ' + item.name+ ' was succesfully stored in the database', life: 3000 };
        }
        if(type == "delete") {
            return { severity: 'info', summary: 'Item Deleted', detail: 'Item ' + item.name + ' was succesfully removed from the database', life: 3000 };
        }
        return {"severity":"info","summary":"Unknown","detail":"Unknown event for "+JSON.stringify(type) + " and " + JSON.stringify(item),"life":3000};
    }

    onAdd = (event) => {
        var attrs=this.props.template.attributes.map((item) => {
            return {
                id: -1,
                name: item.name,
                type: item.type,
                value: ''
            }
        });
        this.setState({item: {id:-1, type: this.props.template.name, attributes:attrs},displayDialog:true});
    }

    renderDialog() {
          return (<div>Test</div>);
//        return (
//            <GenericDialog onClose={this.onClose} onChange={this.onChange} onSave={this.onSave} onDelete={this.onDelete} onLoad={this.onLoad} display={this.state.displayDialog} value={this.state.item} />
//        );
    }

    renderTable(pager) {
        return (<DataTable
          ref={this.dt}
          value={this.state.items}
          className="p-datatable-striped"
          lazy={true} onPage={this.onLazyLoad} loading={this.state.loading}
          paginator={false}
          header={pager}
          footer={pager}
          sortMode="multiple" multiSortMeta={this.state.multiSortMeta} onSort={this.onSort}
          onRowDoubleClick={this.onEdit}
          >
            <Column field="id" header="ID" sortable={true} />
            <Column field="name" header="Name" sortable={true}/>
            <Column field="created" header="Created" sortable={true}/>
            <Column field="modified" header="CModifiedreated" sortable={true}/>
            <Column field="state" header="State" sortable={true}/>
        </DataTable>);
    }
}
